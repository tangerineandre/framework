<?php
namespace Phidias\ORM\Scaffold;

use Phidias\Core\Filesystem;

class Entity
{
    private $classname;
    private $db;
    private $table;
    private $attributes;

    public function __construct($classname)
    {
        $this->classname    = $classname;
        $this->attributes   = array();
    }

    public function setDB($db)
    {
        $this->db = $db;
    }
    
    public function addAttribute($attributeData)
    {
        $this->attributes[$attributeData['Field']] = $attributeData;
    }

    public function fromTable($db, $table)
    {
        $this->table = $table;

        $result = $db->query("DESCRIBE $table");

        while ($row = $result->fetch_assoc()) {
            $this->addAttribute($row);
        }
    }

    public function save($classname, $filename)
    {
        $keyAttributes = array();

        $output = "<?php\n\n";
        $output .= "class $classname extends Phidias\ORM\Entity \n";
        $output .= "{\n";

        foreach ($this->attributes as $attributeName => $attributeData) {
            $output .= "    var \${$attributeName};\n";

            if ($attributeData['Key'] == 'PRI') {
                $keyAttributes[] = $attributeName;
            }

        }

        $output .= "\n";
        $output .= "    protected static \$map = array(\n";
        $output .= "\n";
        
        if ($this->db) {
            $output .= "        'db' => '$this->db',\n";
        }
        
        $output .= "        'table' => '$this->table',\n";
        $output .= "        'keys' => array('".implode("', '", $keyAttributes)."'),\n";
        $output .= "\n";
        $output .= "        'attributes' => array(\n\n";

        foreach ($this->attributes as $attributeName => $attributeData) {

            preg_match_all("/(.+)\((.+)\)/", $attributeData['Type'], $matches);

            if (!isset($matches[1][0])) {
                $type = $attributeData['Type'];
                $length = NULL;
            } else {
                $type   = $matches[1][0];
                $length = $matches[2][0];
            }

            $output .= "            '$attributeName' => array(\n";
            $output .= "                'type' => '$type',\n";
            if ($length) {
                $output .= "                'length' => $length,\n";
            }

            if ($attributeData['Null'] == 'YES') {
                $output .= "                'acceptNull' => TRUE,\n";
            }

            if ($attributeData['Default'] != NULL) {
                $output .= "                'default' => '{$attributeData['Default']}',\n";
            }


            $output .= "            ),\n\n";

        }
        $output .= "        )\n";


        $output .= "    );\n";

        $output .= "}";

        Filesystem::putContents($filename, $output);
    }
}