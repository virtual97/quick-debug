# quick-debug
Automatically exported from http://code.google.com/p/quick-debug


### Describe Magento model magic methods in PHPDoc block
There is a controller to generate Magento model magic methods with tag @method in PHPDoc block.
#### Installation
Copy file `Mage/Core/controllers/CodeController.php` to `your-magento/app/code/core/` with the same path.

#### Using
To request this controller and doc action we need to put similar URL
http://magentostore.local/core/code/doc/
But it requires GET parameter `class`.
##### GET parameter `class` (required)
It must have GET parameter 'class' with value of target class.
##### GET parameter `output`
It means the output way. Default value 'html' that means you will get updated content of the target class as a response.
Value 'file' means the target class will be updated on the server side.

#### Examples
##### Get updated content of class model
http://magentostore.local/core/code/doc/class/Mage_Core_Model_Store

##### Update file of class model on the server directly
http://magentostore.local/core/code/doc/class/Mage_Core_Model_Store/output/file
