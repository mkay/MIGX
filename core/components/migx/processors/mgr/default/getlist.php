<?php

//if (!$modx->hasPermission('quip.thread_list')) return $modx->error->failure($modx->lexicon('access_denied'));

$config = $modx->migx->customconfigs;

$prefix = isset($config['prefix']) && !empty($config['prefix']) ? $config['prefix'] : null;

$packageName = $config['packageName'];

$packagepath = $modx->getOption('core_path') . 'components/' . $packageName . '/';
$modelpath = $packagepath . 'model/';

$modx->addPackage($packageName, $modelpath, $prefix);
$classname = $config['classname'];

$joins = isset($config['joins']) && !empty($config['joins']) ? $modx->fromJson($config['joins']) : false;

$joinalias = isset($config['join_alias']) ? $config['join_alias'] : '';

if (!empty($joinalias)) {
    if ($fkMeta = $modx->getFKDefinition($classname, $joinalias)) {
        $joinclass = $fkMeta['class'];
        $joinfield = $fkMeta[$fkMeta['owner']];
    } else {
        $joinalias = '';
    }
}


if ($modx->lexicon) {
    $modx->lexicon->load($packageName . ':default');
}

/* setup default properties */
$isLimit = !empty($scriptProperties['limit']);
$isCombo = !empty($scriptProperties['combo']);
$start = $modx->getOption('start', $scriptProperties, 0);
$limit = $modx->getOption('limit', $scriptProperties, 20);
$sort = !empty($config['getlistsort']) ? $config['getlistsort'] : 'id';
$sort = $modx->getOption('sort', $scriptProperties, $sort);
$dir = $modx->getOption('dir', $scriptProperties, 'ASC');
$showtrash = $modx->getOption('showtrash', $scriptProperties, '');
$object_id = $modx->getOption('object_id', $scriptProperties, '');
$resource_id = $modx->getOption('resource_id', $scriptProperties, false);
$resource_id = is_object($modx->resource) ? $modx->resource->get('id') : $resource_id;

$resource_id = !empty($object_id) ? $object_id : $resource_id;

if (isset($sortConfig)) {
    $sort = '';
}

$where = $modx->getOption('where', $scriptProperties, '');

$c = $modx->newQuery($classname);
$c->select($modx->getSelectColumns($classname, $classname));

if (!empty($joinalias)) {
    /*
    if ($joinFkMeta = $modx->getFKDefinition($joinclass, 'Resource')){
    $localkey = $joinFkMeta['local'];
    }    
    */
    $c->leftjoin($joinclass, $joinalias);
    $c->select($modx->getSelectColumns($joinclass, $joinalias, 'Joined_'));
}

if ($joins) {
    foreach ($joins as $join) {
        $jalias = $join['alias'];
        if (!empty($jalias)) {
            if (!empty($join['classname'])) {
                $joinclass = $join['classname'];
            } elseif ($fkMeta = $modx->getFKDefinition($classname, $jalias)) {
                $joinclass = $fkMeta['class'];
            } else {
                $jalias = '';
            }
            if (!empty($jalias)) {
                /*
                if ($joinFkMeta = $modx->getFKDefinition($joinclass, 'Resource')){
                $localkey = $joinFkMeta['local'];
                }    
                */
                $selectfields = !empty($join['selectfields']) ? explode(',', $join['selectfields']) : null;
                $on = !empty($join['on']) ? $join['on'] : null;
                $c->leftjoin($joinclass, $jalias, $on);
                $c->select($modx->getSelectColumns($joinclass, $jalias, $jalias . '_', $selectfields));
            }
        }
    }
}

/*
$c->leftjoin('poProduktFormat','ProduktFormat', 'format_id = poFormat.id AND product_id ='.$scriptProperties['object_id']);
//$c->select($classname.'.*');

$c->select('ProduktFormat.format_id,ProduktFormat.calctype,ProduktFormat.price,ProduktFormat.published AS pof_published');
*/

//print_r($config['gridfilters']);

if (isset($config['gridfilters']) && count($config['gridfilters']) > 0) {
    foreach ($config['gridfilters'] as $filter) {

        if (!empty($filter['getlistwhere'])) {

            $requestvalue = $modx->getOption($filter['name'], $scriptProperties, 'all');

            if (isset($scriptProperties[$filter['name']]) && $requestvalue != 'all') {

                $chunk = $modx->newObject('modChunk');
                $chunk->setCacheable(false);
                $chunk->setContent($filter['getlistwhere']);
                $where = $chunk->process($scriptProperties);
                $where = strpos($where, '{') === 0 ? $modx->fromJson($where) : $where;

                $c->where($where);
            }
        }
    }
}


if ($modx->migx->checkForConnectedResource($resource_id, $config)) {
    if (!empty($joinalias)) {
        $c->where(array($joinalias . '.' . $joinfield => $resource_id));
    } else {
        $c->where(array($classname . '.resource_id' => $resource_id));
    }
}


if (!empty($showtrash)) {
    $c->where(array($classname . '.deleted' => '1'));
} else {
    $c->where(array($classname . '.deleted' => '0'));
}

if (!empty($where)) {
    $c->where($modx->fromJson($where));
}

$count = $modx->getCount($classname, $c);

if (empty($sort)) {
    if (is_array($sortConfig)) {
        foreach ($sortConfig as $sort) {
            $sortby = $sort['sortby'];
            $sortdir = isset($sort['sortdir']) ? $sort['sortdir'] : 'ASC';
            $c->sortby($sortby, $sortdir);
        }
    }


} else {
    $c->sortby($sort, $dir);
}
if ($isCombo || $isLimit) {
    $c->limit($limit, $start);
}
//$c->sortby($sort,$dir);
//$c->prepare();echo $c->toSql();
$rows = array();
if ($collection = $modx->getCollection($classname, $c)) {
    foreach ($collection as $object) {
        $row = $object->toArray();
        $rows[] = $row;
    }
}
