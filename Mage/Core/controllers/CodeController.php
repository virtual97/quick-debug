<?php
/**
 * Class for generate PHPDoc @ method tags for DB fields
 */
class Mage_Core_CodeController extends Mage_Core_Controller_Front_Action
{
    const OUTPUT_HTML = 'html';
    const OUTPUT_FILE = 'file';

    function indexAction()
    {
        $classParam = $this->getRequest()->getParam('class'); //class name
        $resourcePrefix = $this->getRequest()->getParam('resource', 'Resource_'); //resource prefix
        $output = $this->getRequest()->getParam('output', self::OUTPUT_HTML); //file - write to file

        if (!strpos($classParam, '_Model_')) {
            throw new Exception("$classParam is not a model.");
        }
        $file = realpath(dirname(__FILE__) . '/../../..') . '/' . str_replace('_', '/', $classParam) . '.php';
        /** @var $model Mage_Core_Model_Abstract */
        $model = new $classParam;

        $fields = $model->getResource()->getReadConnection()->describeTable($model->getResource()->getMainTable());
        unset($fields[$model->getIdFieldName()]);
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
        $classIndex =
        $methodIndex = null;
        foreach ($content as $i => $line) {
            if (0 === strpos($line, ' * @method') && null === $methodIndex) {
                $methodIndex = $i;
            }
            if (0 === strpos($line, 'class')) {
                $classIndex = $i;
                break;
            }
        }
        $defaultMethodsNotExists = array(
            'getCollection',
            'getResourceCollection',
            'getResource',
            '_getResource',
        );

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

            unset($method);
            foreach ($defaultMethodsNotExists as $k => $method) {
                if (strpos($line, $method)) {
                    unset($defaultMethodsNotExists[$k]);
                }
            }

            unset($method);
            foreach ($methodFieldNames as &$method) {
                foreach (array('get', 'set') as $prefix) {
                    $result = (bool) strpos($line, $prefix . $method['name']);
                    if ($result) {
                        $method['exist_' . $prefix] = $result;
                    }
                }
            }
        }

        $strPrefix = ' * @method ';
        $addMethods = array();
        list(, $resourceName) = explode('_Model_', $classParam);
        if ($defaultMethodsNotExists) {
            unset($method);
            foreach ($defaultMethodsNotExists as $method) {
                $class = str_replace($resourceName, '', $classParam);
                if (strpos($method, 'Collection')) {
                    $methodToAdd = $strPrefix . $class . $resourcePrefix .
                            $resourceName . '_Collection ' . $method . '()';
                } else {
                    $methodToAdd = $strPrefix . $class . $resourcePrefix .
                            $resourceName . ' ' . $method . '()';
                }
                if (self::OUTPUT_HTML == $output) {
                    $methodToAdd = "_bb_$methodToAdd _bbc_";
                }
                $addMethods[] = $methodToAdd;
            }
        }

        unset($method);
        foreach ($methodFieldNames as $method) {
            foreach (array('get', 'set') as $prefix) {
                if (!$method['exist_' . $prefix]) {
                    $return = $prefix == 'get' ? $method['type'] : $classParam;
                    $methodTag1 = $prefix . $method['name'] . "()";
                    $result = $return . ' ' . $methodTag1;
                    if ($prefix == 'set') {
                        if (0 === strpos($method['name'], 'is_')) {
                            $varName = 'flag';
                            $method['type'] = 'bool|int';
                        } else {
                            $varName = $method['name'];
                            $varName[0] = strtolower($varName[0]);
                        }
                        $methodTag2 = $prefix . $method['name'] . "({$method['type']} $$varName)";
                        $result .= ' ' . $methodTag2;
                    }
                    $methodToAdd = $strPrefix . $result;
                    if (self::OUTPUT_HTML == $output) {
                        $methodToAdd = "_bb_$methodToAdd _bbc_";
                    }
                    $addMethods[] = $methodToAdd;
                }
            }
        }

        if ($addMethods) {
            if (!$methodIndex) {
                $methodIndex = $classIndex - 2; //back with skip "*/"
            }

            $content[$methodIndex] = $content[$methodIndex] . "\n" . implode("\n", $addMethods);
            $content = implode("\n", $content);

            switch ($output) {
                //write file
                case self::OUTPUT_FILE:
                    $fh = fopen($file, 'w') or die("can't open file");
                    fwrite($fh, $content);
                    fclose($fh);
                    break;

                //generate html
                case self::OUTPUT_HTML:
                    $content = htmlspecialchars($content);
                    $content = str_replace('_bb_', '<b>', $content);
                    $content = str_replace('_bbc_', '</b>', $content);
                    $content = str_replace("\n", '<br/>', $content);
                    $content = str_replace(' ', '&nbsp;', $content);
                    $content = "<div style='font-family: \"Courier New\",Courier,monospace; font-size: 14px;'>$content</div>";
                    echo $content;
                    break;

                default:
                    throw new Exception('Invalid output type');
                    break;
            }
        } else {
            echo 'No any methods to add.';
        }
    }
}
