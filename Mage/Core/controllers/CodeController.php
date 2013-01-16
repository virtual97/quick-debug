<?php
/**
 * Class for generate help code
 */
class Mage_Core_CodeController extends Mage_Core_Controller_Front_Action
{
    /**#@+
     * Output types
     */
    const OUTPUT_HTML = 'html';
    const OUTPUT_FILE = 'file';
    /**#@-*/

    /**
     * Generator PHPDoc @ method tags in a model by DB fields
     *
     * Params:
     * "class" - valid model class name (ex.: "Mage_Core_Model_Example")
     * "output" -
     *      "html" - output as html (default)
     *      "file" - write to file on the server
     * "resource" - resource prefix (by default "Resource_", but can be "Mysql4_" or other)
     *
     * @throws Exception
     */
    function docAction()
    {
        $classParam = $this->_getClassParam();
        $file = $this->_getModelFile($classParam);
        $methodFieldNames = $this->_getMethodFieldNames($classParam);
        $content = $this->_getFileContent($file);

        list($methodIndex, $defaultMethodsNotExists) = $this->_getDefaultMethodsNotExist($content, $methodFieldNames);

        $addMethods = array_merge(
            $this->_getResourceMethods($defaultMethodsNotExists),
            $this->_getParamMethods($methodFieldNames)
        );

        if ($addMethods) {
            $content[$methodIndex] = $content[$methodIndex] . "\n" . implode("\n", $addMethods);
            $content = implode("\n", $content);
            $this->_makeOutput($file, $content);
        } else {
            echo 'No any methods to add.';
        }
    }

    /**
     * @param $content
     * @param $methodFieldNames
     *
     * @return array
     */
    protected function _getDefaultMethodsNotExist($content, &$methodFieldNames)
    {
        list($methodIndex, $classIndex) = $this->_getIndexes($content);
        $defaultMethodsNotExists = $this->_getDefaultMethods();
        foreach ($content as $i => $line) {
            if ($methodIndex < $i && $i > $classIndex) {
                continue;
            }

            if ($classIndex == $i) {
                break;
            }

            if (strpos($line, '@method')) {
                //save last method line
                $methodIndex = $i;
            }


            foreach ($defaultMethodsNotExists as $k => $method) {
                if (strpos($line, $method)) {
                    unset($defaultMethodsNotExists[$k]);
                }
            }
            unset($method);


            foreach ($methodFieldNames as &$method) {
                foreach (array('get', 'set') as $prefix) {
                    if (false !== strpos($line, $prefix . $method['name'])) {
                        $method['exist_' . $prefix] = true;
                    }
                }
            }
            unset($method);
        }
        if (!$methodIndex) {
            $methodIndex = $classIndex - 2;
        }

        return array($methodIndex, $defaultMethodsNotExists);
    }

    /**
     * @return array
     */
    protected function _getDefaultMethods()
    {
        return array(
            'getCollection',
            'getResourceCollection',
            'getResource',
            '_getResource',
        );
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function _getClassParam()
    {
        $classParam = $this->getRequest()->getParam('class'); //class name

        if (!strpos($classParam, '_Model_')) {
            throw new Exception("$classParam is not a model.");
        }

        return $classParam;
    }

    /**
     * @param $file
     *
     * @return array
     * @throws Exception
     */
    protected function _getFileContent($file)
    {
        $content = '';
        if (false === ($fh = fopen($file, 'r'))) {
            throw new Exception('Cannot open ' . $file);
        }
        while (!feof($fh)) {
            $temp = fgets($fh, 4096);
            $content .= $temp;
        }
        fclose($fh);

        $content = explode("\n", $content);

        return $content;
    }

    /**
     * @param $content
     *
     * @return array
     */
    protected function _getIndexes($content)
    {
        $classIndex = $methodIndex = null;
        foreach ($content as $i => $line) {
            if (0 === strpos($line, ' * @method') && null === $methodIndex) {
                $methodIndex = $i;
            }
            if (0 === strpos($line, 'class')) {
                $classIndex = $i;
                break;
            }
        }

        return array($methodIndex, $classIndex);
    }

    /**
     * @param $classParam
     *
     * @return string
     */
    protected function _getModelFile($classParam)
    {
        return realpath(dirname(__FILE__) . '/../../..') . '/' . str_replace('_', '/', $classParam) . '.php';
    }

    /**
     * @param $classParam
     *
     * @return mixed
     * @throws Exception
     */
    protected function _getMethodFieldNames($classParam)
    {
        /** @var $model Mage_Core_Model_Abstract */
        $model = new $classParam;

        $resource = $model->getResource();
        if (!is_object($resource)) {
            throw new Exception('Model does not have a resource.');
        }

        $fields = $resource->getReadConnection()->describeTable($model->getResource()->getMainTable());
        unset($fields[$model->getIdFieldName()]);

        $methodFieldNames = array();
        foreach ($fields as $f) {
            $name = $f['COLUMN_NAME'];
            $len = strlen($name);
            for ($x = 0; $x < $len; $x++) {
                if ('_' == $name[$x]) {
                    $name[$x + 1] = strtoupper($name[$x + 1]);
                }
            }
            $name = str_replace('_', '', $name);
            $name[0] = strtoupper($name[0]);
            $return = false === strpos($f['DATA_TYPE'], 'int') ?  'string' : 'int';
            $methodFieldNames[$f['COLUMN_NAME']] = array(
                'name' => $name,
                'type' => $return,
                'exist_get' => false,
                'exist_set' => false);
        }

        return $methodFieldNames;
    }

    /**
     * @param $file
     * @param $content
     * @throws Exception
     */
    protected function _makeOutput($file, $content)
    {
        $output = $this->_getOutputType(); //file - write to file
        switch ($output) {
            //write file
            case self::OUTPUT_FILE:
                $fh = fopen($file, 'w') or die("can't open file");
                fwrite($fh, $content);
                fclose($fh);
                break;

            //generate html
            case self::OUTPUT_HTML:
                $content = $this->_prepareContent($content);
                echo $content;
                break;

            default:
                throw new Exception('Invalid output type');
                break;
        }
    }

    /**
     * @return mixed
     */
    protected function _getOutputType()
    {
        return $this->getRequest()->getParam('output', self::OUTPUT_HTML);
    }

    /**
     * @param $methodFieldNames
     * @return array
     */
    protected function _getParamMethods($methodFieldNames)
    {
        $output = $this->_getOutputType();
        $classParam = $this->getRequest()->getParam('class');
        $addMethods = array();
        $strPrefix = ' * @method ';
        foreach ($methodFieldNames as $method) {
            foreach (array('get', 'set') as $prefix) {
                if (!$method['exist_' . $prefix]) {
                    $return     = $prefix == 'get' ? $method['type'] : $classParam;
                    $methodTag1 = $prefix . $method['name'] . '(%s)';
                    $result     = $return . ' ' . $methodTag1;
                    if ($prefix == 'set') {
                        if (0 === strpos($method['name'], 'is_')) {
                            $varName        = 'flag';
                            $method['type'] = 'bool|int';
                        } else {
                            $varName    = $method['name'];
                            $varName[0] = strtolower($varName[0]);
                        }
                        $param  = "{$method['type']} $$varName";
                    } else {
                        $param = '';
                    }
                    $result = sprintf($result, $param);
                    $methodToAdd = $strPrefix . $result;
                    if (self::OUTPUT_HTML == $output) {
                        $methodToAdd = "_bb_$methodToAdd _bbc_";
                    }
                    $addMethods[] = $methodToAdd;
                }
            }
        }

        return $addMethods;
    }

    /**
     * @param $defaultMethodsNotExists
     * @return array
     */
    protected function _getResourceMethods($defaultMethodsNotExists)
    {
        $resourcePrefix = $this->getRequest()->getParam('resource', 'Resource_'); //resource prefix
        $output = $this->_getOutputType();
        $classParam = $this->getRequest()->getParam('class');
        $strPrefix = ' * @method ';
        $addMethods = array();
        list(, $resourceName) = explode('_Model_', $classParam);
        if ($defaultMethodsNotExists) {
            unset($method);
            foreach ($defaultMethodsNotExists as $method) {
                $module = str_replace($resourceName, '', $classParam);
                $methodToAdd = $strPrefix;
                if (strpos($method, 'Collection')) {
                    $className = $module . $resourcePrefix . $resourceName . '_Collection';
                    if (!@class_exists($className)) {
                        continue;
                    }
                    $methodToAdd .= $className . ' ' . $method . '()';
                } else {
                    $className = $module . $resourcePrefix . $resourceName;
                    if (!@class_exists($className)) {
                        continue;
                    }
                    $methodToAdd .= $className . ' ' . $method . '()';
                }
                if (self::OUTPUT_HTML == $output) {
                    $methodToAdd = "_bb_$methodToAdd _bbc_";
                }
                $addMethods[] = $methodToAdd;
            }
        }

        return $addMethods;
    }

    /**
     * Modules enabler generator action for enable all or desabled modules
     *
     * For use enable only disabled modules use parameter all=0
     * Make file /app/etc/modules/ZEnabler.xml and paste generated code in it.
     * File must be in the end by alphabetic sort
     */
    public function moduleEnablerAction()
    {
        $modules = (array) Mage::getConfig()->getNode('modules')->children();
        $xml = '<?xml version="1.0"?>' . PHP_EOL;
        $xml .= '<config>' . PHP_EOL;
        $xml .= '<modules>' . PHP_EOL;
        $all = $this->getRequest()->getParam('all', 0);
        $cnt = 0;
        foreach ($modules as $name => $node) {
            if ($all == 1 || $node->active == 'false') {
                $cnt++;
                $xml .= str_repeat(' ', 4) . "<$name><active>true</active></$name>" . PHP_EOL;
            }
        }
        if (!$cnt) {
            echo 'No any modules to enable.';
            return;
        }
        $xml .= '</modules>' . PHP_EOL;
        $xml .= '</config>' . PHP_EOL;
        $xml = $this->_prepareContent($xml);
        echo $xml;
    }

    /**
     * Prepare content to echo
     *
     * @param string $content
     * @return string   Return prepared content
     */
    protected function _prepareContent($content)
    {
        $content = htmlspecialchars($content);
        $content = str_replace('_bb_', '<b>', $content);
        $content = str_replace('_bbc_', '</b>', $content);
        $content = str_replace("\n", '<br/>', $content);
        $content = str_replace(' ', '&nbsp;', $content);
        $content = "<div style='font-family: \"Courier New\",Courier,monospace; font-size: 14px;'>$content</div>";
        return $content;
    }
}
