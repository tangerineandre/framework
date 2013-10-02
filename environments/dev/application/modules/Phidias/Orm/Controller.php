<?php
use Phidias\Core\Controller;
use Phidias\Core\Environment;
use Phidias\Core\Filesystem;

class Phidias_Orm_Controller extends Controller
{

    /* Return a dependency-organized array of entities */
    private function findEntities(&$retval, $folder, $basename = NULL)
    {
        $files = Filesystem::listDirectory($folder, TRUE, FALSE);
        foreach ($files as $file) {
            if ($file !== 'Entity.php') {
                continue;
            }

            $classname = $basename.str_replace('.php', '', $file);

            $object = new $classname;
            $map    = $object->getMap();

            $relations = array();
            if (isset($map['relations'])) {
                foreach ($map['relations'] as $relationData) {
                    $relations[] = $relationData['entity'];
                }
            }

            $retval[$classname] = array(
                'class'     => $classname,
                'relations' => $relations
            );

        }

        $subfolders = Filesystem::listDirectory($folder, FALSE, TRUE);
        foreach ($subfolders as $subfolder) {
            $this->findEntities($retval, $folder.'/'.$subfolder, $basename.$subfolder.'_');
        }
    }

    private function organizeEntity($name, &$index, &$organized)
    {
        if (isset($index[$name]['seen'])) {
            return;
        }
        $index[$name]['seen'] = $name;

        if (isset($index[$name]['relations'])) {
            foreach ($index[$name]['relations'] as $relatedEntity) {
                $this->organizeEntity($relatedEntity, $index, $organized);
            }
        }

        $organized[] = $name;
    }

    public function install()
    {
        $environmentStack = Environment::getStack();

        $entities = array();
        $this->findEntities($entities, $environmentStack[0].'/application/modules');
        $organized = array();

        foreach (array_keys($entities) as $entityName) {
            $this->organizeEntity($entityName, $entities, $organized);
        }

        foreach (array_reverse($organized) as $entity) {
            $table = $entity::table();
            $table->drop();
        }

        try {
            foreach ($organized as $entity) {
                $table = $entity::table();
                $table->create();
            }
        } catch (Exception $e) {
            dumpx($e);
        }


    }
}