<?php
/**
 * TaggerGetTags
 *
 * DESCRIPTION
 *
 * This Snippet allows you to list tags for resource(s), group(s) and all tags
 *
 * PROPERTIES:
 *
 * &resources       string  optional    Comma separated list of resources for which will be listed Tags
 * &groups          string  optional    Comma separated list of Tagger Groups for which will be listed Tags
 * &rowTpl          string  optional    Name of a chunk that will be used for each Tag. If no chunk is given, array with available placeholders will be rendered
 * &outTpl          string  optional    Name of a chunk that will be used for wrapping all tags. If no chunk is given, tags will be rendered without a wrapper
 * &separator       string  optional    String separator, that will be used for separating Tags
 * &limit           int     optional    Limit number of returned tag Tags
 * &offset          int     optional    Offset the output by this number of Tags
 * &totalPh         string  optional    Placeholder to output the total number of Tags regardless of &limit and &offset
 * &target          int     optional    An ID of a resource that will be used for generating URI for a Tag. If no ID is given, current Resource ID will be used
 * &showUnused      int     optional    If 1 is set, Tags that are not assigned to any Resource will be included to the output as well
 * &showUnpublished int     optional    If 1 is set, Tags that are assigned only to unpublished Resources will be included to the output as well
 * &showDeleted     int     optional    If 1 is set, Tags that are assigned only to deleted Resources will be included to the output as well
 * &contexts        string  optional    If set, will display only tags for resources in given contexts. Contexts can be separated by a comma
 * &toPlaceholder   string  optional    If set, output will return in placeholder with given name
 *
 * USAGE:
 *
 * [[!TaggerGetTags? &showUnused=`1`]]
 *
 *
 * @package tagger
 */

$tagger = $modx->getService('tagger','Tagger',$modx->getOption('tagger.core_path',null,$modx->getOption('core_path').'components/tagger/').'model/tagger/',$scriptProperties);
if (!($tagger instanceof Tagger)) return '';

$resources = $modx->getOption('resources', $scriptProperties, '');
$groups = $modx->getOption('groups', $scriptProperties, '');
$target = (int) $modx->getOption('target', $scriptProperties, $modx->resource->id, true);
$showUnused = (int) $modx->getOption('showUnused', $scriptProperties, '0');
$showUnpublished = (int) $modx->getOption('showUnpublished', $scriptProperties, '0');
$showDeleted = (int) $modx->getOption('showDeleted', $scriptProperties, '0');
$contexts = $modx->getOption('contexts', $scriptProperties, '');

/* called in the tag item loop to initialize value prior to Nth templating
$rowTpl = $modx->getOption('rowTpl', $scriptProperties, '');
*/

$outTpl = $modx->getOption('outTpl', $scriptProperties, '');
$separator = $modx->getOption('separator', $scriptProperties, '');
$limit = intval($modx->getOption('limit', $scriptProperties, ''));
$offset = intval($modx->getOption('offset', $scriptProperties, ''));
$totalPh = $modx->getOption('totalPh', $scriptProperties, 'tags_total');

$resources = $tagger->explodeAndClean($resources);
$groups = $tagger->explodeAndClean($groups);
$contexts = $tagger->explodeAndClean($contexts);
$toPlaceholder = $modx->getOption('toPlaceholder', $scriptProperties, '');

$c = $modx->newQuery('TaggerTag');

$c->select($modx->getSelectColumns('TaggerTag', 'TaggerTag'));
$c->select($modx->getSelectColumns('TaggerGroup', 'Group', 'group_'));

$c->leftJoin('TaggerTagResource', 'Resources');
$c->leftJoin('TaggerGroup', 'Group');
$c->leftJoin('modResource', 'Resource', array('Resources.resource = Resource.id'));

if (!empty($contexts)) {
    $c->where(array(
        'Resource.context_key:IN' => $contexts
    ));
}

if ($showUnpublished == 0) {
    $c->where(array(
        'Resource.published' => 1
    ));
}

if ($showDeleted == 0) {
    $c->where(array(
        'Resource.deleted' => 0
    ));
}

if ($resources) {
    $c->where(array(
        'Resources.resource:IN' => $resources
    ));
}

if ($groups) {
    $c->where(array(
        'Group.id:IN' => $groups,
        'OR:Group.name:IN' => $groups
    ));
}

$c->groupby($modx->getSelectColumns('TaggerTag', 'TaggerTag') . ',' . $modx->getSelectColumns('TaggerGroup', 'Group'));
$tags = $modx->getIterator('TaggerTag', $c);

$modx->setPlaceholder($totalPh, iterator_count($tags));
$out = array();

$friendlyURL = $modx->getOption('friendly_urls', null, 0);
$tagKey = $modx->getOption('tagger.tag_key', null, 'tags');

// prep for &tpl_N
$keys = array_keys($scriptProperties);
$intTpls = array();
foreach($keys as $key) {
  $keyBits = explode('_', $key);
  if ($keyBits[0] === 'tpl') {
    if ($i = (int) $keyBits[1]) $intTpls[$i] = $scriptProperties[$key];
  }
}
ksort($intTpls);

$count = 0;
$idx = 1;
foreach ($tags as $tag) {
    if ($offset && $count < $offset) {
        $count++;
        continue;
    }
    
    $phs = $tag->toArray();
    $phs['cnt'] = $modx->getCount('TaggerTagResource', array('tag' => $tag->id));

    if (($showUnused == 0) && ($phs['cnt'] == 0)) {
        continue;
    }

    if ($friendlyURL == 1) {
        $uri = $modx->makeUrl($target, '', '') . '/' . $tagKey . '/' . $tag->tag;
        $uri = str_replace('//', '/', $uri);
    } else {
        $uri = $modx->makeUrl($target, '', 'tags=' . $tag->tag);
    }

    $phs['uri'] = $uri;
    $phs['idx'] = $idx;

    $rowTpl = $modx->getOption('rowTpl', $scriptProperties, '');
    if ($rowTpl == '') {
        $out[] = '<pre>' . print_r($phs, true) . '</pre>';
    } else {
        foreach ($intTpls as $int => $tpl) {
            if ( $idx === $int ) {
                $rowTpl = $tpl; 
                break;
            }
            if ( ($idx % $int) === 0 ) $rowTpl = $tpl;
        }
        $out[] = $modx->getChunk($rowTpl, $phs);
    }
    
    if ($limit && $idx === $limit) {
        break;
    }
    $idx++;
}

$out = implode($separator, $out);

if ($outTpl != '') {
    $out = $modx->getChunk($outTpl, array('tags' => $out));
}

if (!empty($toPlaceholder)) {
    $modx->setPlaceholder($toPlaceholder, $out);
    return '';
}

return $out;
