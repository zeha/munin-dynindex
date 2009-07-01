<?php
/* vim:set et sw=4 ts=4: */
define('MUNIN_HTML', '/munin/');
define('MUNIN_GRAPH', '/cgi-bin/munin-cgi-graph/');
define('MUNIN_GRAPH_IMG', '/%s-%s.png');
define('MUNIN_DATADIR', '/var/lib/munin/');

$graphnames = array();

$hosts = array();
$lines = file(MUNIN_DATADIR.'datafile');
foreach($lines as $line_num => $line) {
    $line = substr($line, 0, -1);

    $tmp = split('\;', $line);
    if (count($tmp) != 2) continue; // ignore config settings
    $group = $tmp[0];
    $tmp = split('\:', $tmp[1], 2);
    $host = $tmp[0];
    $tmp = split('\.', $tmp[1], 2);
    $graphname = $tmp[0];
    $tmp = split('\ ', $tmp[1], 2);
    $graphcfgkey = $tmp[0];
    $graphcfgvalue = $tmp[1];

    if (!$hosts[$host]) { $hosts[$host] = array('graphs'=>array()); }
    $hosts[$host]['group'] = $group;
    if ($graphcfgkey == 'graph_title') {
        $hosts[$host]['graphs'][] = $graphname;
        $graphnames[$graphname] = $graphcfgvalue;
    }
}

$hostgroups = array();
$possible_hostgroups = array();

foreach($hosts as $host => $hostconfig) {
    $group = $hostconfig['group'];
    if (strpos($host,'-') != 3) {
        // group name modifications go here
        $hostgroups[$group][] = $host;
        continue;
    }
    $group = substr($host,0,3);
    $hostgroups[$group][] = $host;

    if (strpos($host,'-',4) !== FALSE) {
        $pos = strpos($host,'-',4);
        $group = substr($host, 0, $pos);
        $hostgroups[$group][] = $host;

        # create possible hostgroups
	$possible_hostgroups[substr($host,0,-2)][] = $host;
	if (substr($host,9,3) == 'foo') { $possible_hostgroups[substr($host,0,12)][] = $host; }
	if (substr($host,9,5) == 'zzzzz') { $possible_hostgroups[substr($host,0,14)][] = $host; }
    } else {
        $hostgroups[$group.'-infra'][] = $host;
    }
}
foreach($possible_hostgroups as $hostgroup => $hostgroupdata) {
    if (count($possible_hostgroups[$hostgroup]) > 1) {
        $hostgroups[$hostgroup] = $possible_hostgroups[$hostgroup];
    }
}

function get_host_html_path($host) {
    return $_SERVER['SCRIPT_NAME'].'/'.$host;
}
function get_graph_html_path($host, $graph) {
    global $hosts;
    return MUNIN_HTML.$hosts[$host]['group'].'/'.$host.'-'.$graph;
}
function get_graph_img_path($host, $graph, $time) {
    global $hosts;
    return MUNIN_GRAPH.$hosts[$host]['group'].'/'.$host.'/'.$graph.'-'.$time;
}

$_SERVER['PATH_INFO'] = trim($_SERVER['PATH_INFO'], '/');

$title = 'Munin';
if (strlen($_SERVER['PATH_INFO']) > 1) {
	$title = 'Munin for '.$_SERVER['PATH_INFO'];
}

if ($_SERVER['PATH_INFO'] == 'custom') {
	$hostgroups['custom'] = split(',', $_GET['hosts']);
}
if (!isset($hostgroups[$_SERVER['PATH_INFO']])) {
	if (isset($hosts[$_SERVER['PATH_INFO']])) {
		$hostgroups[$_SERVER['PATH_INFO']] = array($_SERVER['PATH_INFO']);
	}
}

ksort($hostgroups);
?>

<html>
<head>
<title><?=$title?></title>
<link rel="stylesheet" href="/munin/style.css" type="text/css" />
<style type="text/css">
    body, tr, input, select { font-size: 90%; }
    h1, h2, h3, h4 { margin-bottom: 0; }
    div.left { float:5 left; text-align: center; width: 500px; max-height: 510px; overflow: hidden; }
    h1 { font-size: 150%; }
</style>
</head>
<body>

 <table cellpadding="3" border="0">
  <tr>
     <td><div class="logo">&nbsp;</div></td>
     <td valign="top">
     <h1><?=$title?></h1>
<small><? system('uptime'); ?></small><br>
<small>All times are UTC.</small>
</td>
    </tr>
 </table>

    <form id="form" method="get" action="?" onsubmit="javascript:if(document.getElementById('hosts').value!='') { document.getElementById('form').action='custom?'; }">
<p>Groups: 
    <? foreach (array_keys($hostgroups) as $group) : ?>
        <? if($_SERVER['PATH_INFO'] == $group) : ?>
            <?=$group?>
        <? else: ?>
            <a href="<?=$_SERVER['SCRIPT_NAME']?>/<?=$group?>?<?=$_SERVER['QUERY_STRING']?>"><?=$group?></a>
        <? endif; ?>
    <? endforeach; ?>
    custom group: <input type=text name=hosts id=hosts title="seperate hosts with commas" size=10 value="<? if (isset($_GET['hosts'])) { echo $_GET['hosts']; } ?>" />
</p>

<? if (!isset($hostgroups[$_SERVER['PATH_INFO']])) : ?>
    
    <? if (strlen($_SERVER['PATH_INFO'])) : ?>
        <p>Unkown group: '<?=$_SERVER['PATH_INFO']?>'</p>
    <? endif; ?>
    
<? else : ?>
    <?php
        $group = $_SERVER['PATH_INFO'];
        sort($hostgroups[$group]); // need this for stable host sorting

        $this_graphs = array();
        foreach($hostgroups[$group] as $host) {
            $this_graphs = array_merge($this_graphs, $hosts[$host]['graphs']);
	}
	$this_graphs = array_unique($this_graphs);
	sort($this_graphs);

        $this_graphnames = array();
        foreach($this_graphs as $graph) {
            $this_graphnames[$graph] = $graphnames[$graph];
	}

        $options = array(
            'time' => array('day','week','month','year'),
            'type' => $this_graphnames,
        );

        if (!isset($_GET['time'])) { $_GET['time'] = 'day'; }


    ?>
        <? foreach ($options as $name => $opts) : ?>
            <label for="<?=$name?>"><?=ucwords($name)?>:</label>
            <select name="<?=$name?>" id="<?=$name?>">
                <option value="">Select...</option>
                <? foreach ($opts as $key => $val) : ?>
                    <option <?=isset($_GET[$name])&&$_GET[$name]==(is_numeric($key)?$val:$key)?'selected="selected" ':''?>value="<?=is_numeric($key)?$val:$key?>"><?=$val?></option>
                <? endforeach; ?>
            </select>
        <? endforeach; ?>
        &mdash;
        <input type="submit" />

    <? if (isset($_GET['time'], $_GET['type']) && $_GET['type'] != '') : ?>
    <? $time = $_GET['time']; $graph = $_GET['type']; ?>
        <h2><?=$options['type'][$_GET['type']]?></h2>
        <? foreach($hostgroups[$group] as $host) : ?>
            <div class="left">
            <h3><a href="<?=get_host_html_path($host)?>"><?=$host?></a>: <a href="<?=get_graph_html_path($host, $graph)?>"><?=$graphnames[$graph]?></a></h3>
            <img src="<?=get_graph_img_path($host, $graph, $time)?>" alt="" />
            </div>
        <? endforeach; ?>
    <? else : ?>
    <? if (isset($_GET['time'])) : ?>
    <? $time = $_GET['time']; ?>
        <table>
        <? foreach($this_graphs as $graph) : ?>
        <tr>
        <? foreach($hostgroups[$group] as $host) : ?>
	<td valign=top>
	<? if (in_array($graph, $hosts[$host]['graphs'])) : ?>
	    <h3><a href="<?=get_host_html_path($host)?>"><?=$host?></a>: <a href="<?=get_graph_html_path($host, $graph)?>"><?=$graphnames[$graph]?></a> 
            </h3>
	    <img src="<?=get_graph_img_path($host, $graph, $time)?>" alt="" />
	<? else : ?>
	    <small>(No <?=$graph?> graph for <?=$host?>.)</small>
	<? endif; ?>
        </td>
        <? endforeach; ?>
        </tr>
        <? endforeach; ?>
        </table>

    <? endif; ?>
    <? endif; ?>
    
<? endif; ?>
    </form>

<br clear=all />
<br clear=all />

</body>
</html>

