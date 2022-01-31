<!DOCTYPE html>
<!--
  - Main PHP file of nw-page-editor web edition.
  -
  - @version $Version: 2022.01.31$
  - @author Mauricio Villegas <mauricio_ville@yahoo.com>
  - @copyright Copyright(c) 2015-present, Mauricio Villegas <mauricio_ville@yahoo.com>
  - @license MIT License
  -->

<?php
require_once('common.inc.php');

header('Cache-Control: no-store, must-revalidate');

// Check that document(s) source is specified, paths exists and have the correct extension //
$num_sources = (isset($_GET['f'])?1:0) + (isset($_GET['l'])?1:0) + (isset($_GET['d'])?1:0);
if ( $num_sources == 0 ) {
  echo 'error: either an xml file (via f=...) or a list (via l=...) or directory (via d=...) has to be specified';
  exit;
}
if ( $num_sources > 1 ) {
  echo 'error: only one source among an xml file, a list file or a directory can be given';
  exit;
}
if ( isset($_GET['f']) ) {
  if ( ! file_exists('../data/'.$_GET['f']) ) {
    echo 'error: xml file not found: '.$_GET['f'];
    exit;
  }
  if ( ! preg_match('/\.xml$/',$_GET['f']) ) {
    echo 'error: expected file to have an .xml extension: '.$_GET['f'];
    exit;
  }
}
if ( isset($_GET['l']) ) {
  if ( ! file_exists('../data/'.$_GET['l']) ) {
    echo 'error: list file not found: '.$_GET['l'];
    exit;
  }
  if ( ! preg_match('/\.lst$/',$_GET['l']) ) {
    echo 'error: expected file to have a .lst extension: '.$_GET['l'];
    exit;
  }
}
if ( isset($_GET['d']) && ! is_dir('../data/'.$_GET['d']) ) {
  echo 'error: directory not found: '.$_GET['d'];
  exit;
}

// Create list of files //
if ( isset($_GET['f']) ) {
  $thelist = array("'../data/".$_GET['f']."'");
}
elseif ( isset($_GET['l']) ) {
  $thelist = explode("\n",trim(file_get_contents('../data/'.$_GET['l'])));
  array_walk( $thelist, function ( &$item ) { $item = "'../data/".$item."'"; } );
}
elseif ( isset($_GET['d']) ) {
  $thelist = glob('../data/'.$_GET['d'].'/*.xml');
  array_walk( $thelist, function ( &$item ) { $item = "'".$item."'"; } );
}

$pagenum = isset($_GET['n']) ? intval($_GET['n']) : 0;
if ( $pagenum > count($thelist) ) {
  echo 'error: page number higher than current list size';
  exit;
}

// Export variables to javascript //
$script = "<script>\n";
$script .= "var page_editor_version='nw-page-editor_v$version';\n";
$script .= "var uname = '".$uname."';\n";
$script .= "var brhash = '".$_COOKIE['PHP_AUTH_BR']."';\n";
$script .= "var list_xmls = [ ".implode(', ',$thelist)." ];\n";
$script .= "</script>\n";

// Load provided JS and CSS files //
$provided_js_files = '';
if ( getenv('JS') !== false ) {
  foreach ( explode(' ', getenv('JS')) as $js ) {
    $provided_js_files .= '$.getScript("../data/'.$js.'");'."\n";
  }
  $provided_js_files = '$(window).on("load", function () { '.$provided_js_files.' });'."\n";
  $provided_js_files = '<script type="text/javascript">'.$provided_js_files.'</script>'."\n";
}
$provided_css_files = '';
if ( getenv('CSS') !== false ) {
  foreach ( explode(' ', getenv('CSS')) as $css ) {
    $provided_css_files .= '<link type="text/css" rel="stylesheet" href="../data/'.$css.'"/>'."\n";
  }
}
?>

<html>
<head>
  <meta charset="UTF-8"/>
  <title>nw-page-editor v<?=$version?> - <?=$uname?></title>
  <link rel="icon" href="data:;base64,iVBORw0KGgo="/>
  <link type="text/css" rel="stylesheet" id="page_styles" href="../css/page-editor.css<?=$v?>"/>
  <link type="text/css" rel="stylesheet" href="../node_modules/github-markdown-css/github-markdown.css"/>
  <?=$provided_css_files?>
  <script type="text/javascript" src="../js/jquery-3.2.1.min.js"></script>
  <script type="text/javascript" src="../js/jquery.stylesheet-0.3.7.min.js"></script>
  <script type="text/javascript" src="../js/interact-1.3.4.min.js"></script>
  <script type="text/javascript" src="../js/mousetrap-1.6.2.min.js"></script>
  <script type="text/javascript" src="../js/marked-4.0.12.min.js"></script>
  <script type="text/javascript" src="../js/tiff-2016-11-01.min.js"></script>
  <script type="text/javascript" src="../js/pdfjs-1.8.579.min.js"></script>
  <script type="text/javascript" src="../js/turf-5.1.6.min.js"></script>
  <script type="text/javascript" src="../js/svg-canvas.js<?=$v?>"></script>
  <script type="text/javascript" src="../js/page-canvas.js<?=$v?>"></script>
  <script type="text/javascript" src="../js/page-editor.js<?=$v?>"></script>
  <?=$script?>
  <script type="text/javascript" src="../js/web-app.js<?=$v?>"></script>
  <?=$provided_js_files?>
</head>
<body>
  <div id="container">
    <div id="statusBar">
      <button id="prevPage" disabled="" class="tooltip-down" data-tooltip="previous document">⇐</button>
      <div id="pageNumWrap">
        <input type="text" id="pageNum" class="mousetrap" value="<?=$pagenum?>" disabled=""/>/<span id="totPages">0</span>
      </div>
      <button id="nextPage" disabled="" class="tooltip-down" data-tooltip="next document">⇒</button>
      <div id="textFilter" style="display:none;">
        <b class="tooltip-down" data-tooltip="Keep only elements containing some text and/or matching css selector&#xa;(e.g. lorem ipsum [id^=reg1]) or if starting with '!' an xpath (e.g.&#xa;!//_:g[*[@value=&quot;printed&quot;]])">Filter:</b>
        <input name="filter" type="text" placeholder="text or css/xpath selector" class="mousetrap"/>
        <button id="clearFilter" tabindex="-1">X</button>
      </div>
      <div id="stateInfo">
        <b><span id="modeActive"></span> (<span id="modeElement">-</span>):</b> <span id="selectedType">-</span> <span id="selectedId">-</span>
        <b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;File: </b><span id="xmlFile"></span>
      </div>
      <button id="drawerButton" class="menu-toggle c-hamburger c-hamburger--htx">
        <span>toggle menu</span>
      </button>
    </div>
    <div id="xpg" class="page_container"></div>
    <textarea id="textedit" class="mousetrap" placeholder="Text edit box" dir="auto" disabled=""></textarea>
    <div id="textinfo"></div>
  </div>

  <div id="cursor" class="cursor-right">
    <span id="cursorX">-</span>,<span id="cursorY">-</span>
  </div>

  <div id="drawer">
    <fieldset id="generalFieldset">
      <button id="saveFile" disabled="">Save</button>
      <button id="openReadme">Readme</button>
      <span><b>User: </b><?php echo $uname; if ( isset($_COOKIE['PHP_AUTH_USER']) && $uname === $_COOKIE['PHP_AUTH_USER'] ) echo ' (<a href="logout.php">logout</a>)';?></span>
      <label id="autoSave"><input class="mousetrap" type="checkbox"/> Auto-save</label>
      <label id="centerSelected"><input class="mousetrap" type="checkbox"/> Center on selection</label>
      <label id="xmlTextValidate"><input class="mousetrap" type="checkbox"/> Validate text as XML</label>
    </fieldset>
    <fieldset id="editModesFieldset">
      <legend class="tooltip-down" data-tooltip="Change via keyboard:&#xa;&#xa0;&#xa0;ctrl[+shift]+, ctrl[+shift]+.">Edit modes</legend>
      <div class="radio-set">
        <label id="selMode"><input class="mousetrap" type="radio" name="mode2" value="select" checked=""/> Select</label>
        <label id="baseMode"><input class="mousetrap" type="radio" name="mode2" value="baseline"/> Baseline</label>
        <label id="coorMode"><input class="mousetrap" type="radio" name="mode2" value="coords"/> Coords</label>
        <label id="dragMode"><input class="mousetrap" type="radio" name="mode2" value="drag"/> Drag</label>
        <label id="modifyMode"><input class="mousetrap" type="radio" name="mode2" value="modify"/> Modify</label>
        <label id="createMode"><input class="mousetrap" type="radio" name="mode2" value="create"/> Create</label>
        <label id="textMode"><input class="mousetrap" type="checkbox"/> Text editable</label>
        <label id="editAfterCreate"><input class="mousetrap" type="checkbox" checked=""/> Edit mode after create</label>
      </div>
      <div class="radio-set">
        <label id="pageMode"><input class="mousetrap" type="radio" name="mode1" value="page"/> Page</label>
        <label id="regMode"><input class="mousetrap" type="radio" name="mode1" value="region"/> TextRegion</label>
        <label id="lineMode"><input class="mousetrap" type="radio" name="mode1" value="line" checked=""/> TextLine</label>
        <label id="wordMode"><input class="mousetrap" type="radio" name="mode1" value="word"/> Word</label>
        <label id="glyphMode"><input class="mousetrap" type="radio" name="mode1" value="glyph"/> Glyph</label>
        <label id="tabMode"><input class="mousetrap" type="radio" name="mode1" value="table"/> Table</label>
        <label id="groupMode"><input class="mousetrap" type="radio" name="mode1" value="group"/> Group</label>
        <label id="otherMode" class="tooltip-up" data-tooltip="ImageRegion, SeparatorRegion,&#xa;CustomRegion or any custom type">
          <input class="mousetrap" type="radio" name="mode1" value="other"/>
          <input class="mousetrap" list="other-regions" value="ImageRegion"/>
          <datalist id="other-regions">
            <option value="ImageRegion"></option>
            <option value="SeparatorRegion"></option>
            <option value="CustomRegion"></option>
          </datalist>
        </label>
        <label id="allMode"><input class="mousetrap" type="radio" name="mode1" value="all"/> All</label>
      </div>
    </fieldset>
    <fieldset id="newPropsFieldset">
      <legend>Options for edit/create modes</legend>
      <div><label id="roundPoints"><input class="mousetrap" type="checkbox" checked=""/> Round points</label></div>
      <div><label id="axisAligned"><input class="mousetrap" type="checkbox"/> Restrict to axis aligned</label></div>
      <div>
        Coords:
        <select id="coordsRestriction" name="coordsRestriction">
          <option value="4">4 side polygon</option>
          <option value="3+">≥3 side polygon</option>
        </select>
      </div>
      <div>
        TextLine:
        <select id="textlineRestriction" name="textlineRestriction">
          <option value="1">1 segment baseline</option>
          <option value="1+">≥1 segments baseline</option>
          <option value="4">4 side polygon</option>
          <option value="3+">≥3 side polygon</option>
        </select>
      </div>
      <div>
        Baseline orientation:
        <label id="orient-u"><input class="mousetrap" type="radio" name="line-orient" value="u" checked=""/> →</label>
        <label id="orient-l"><input class="mousetrap" type="radio" name="line-orient" value="l"/> ↑</label>
        <label id="orient-r"><input class="mousetrap" type="radio" name="line-orient" value="r"/> ↓</label>
        <label id="orient-a"><input class="mousetrap" type="radio" name="line-orient" value="a"/> any</label>
      </div>
      <div>
        Baseline offset:
        <select id="baselineOffset" name="baselineOffset">
          <option value="0.25">0.25</option>
          <option value="0.00">0.00</option>
        </select>
      </div>
      <div>
        Table size:
        <label id="table-rows"><input class="mousetrap" type="number" name="table-rows" min="1" value="3"/> rows</label>
        <label id="table-cols"><input class="mousetrap" type="number" name="table-cols" min="1" value="3"/> columns</label>
      </div>
      <div>
        Group size:
        <label id="group-size-min"> from
          <input class="mousetrap" type="number" name="group-size-min" min="1" value="1"/>
        </label>
        <label id="group-size-max"> to
          <input class="mousetrap" type="number" name="group-size-max" min="0" value=""/>
          members
        </label>
      </div>
      <div>
        Group creation type:
        <label id="group-init-c"><input class="mousetrap" type="radio" name="group-init" value="click"/> click</label>
        <label id="group-init-b"><input class="mousetrap" type="radio" name="group-init" value="box" checked=""/> box</label>
        <label id="group-init-p"><input class="mousetrap" type="radio" name="group-init" value="polygon"/> polygon</label>
      </div>
      <div>
        Group member type:
        <select id="group-member-type" name="group-member-type">
          <option value=".Page">Page</option>
          <option value=".TextRegion">TextRegion</option>
          <option value=".TextLine" selected="">TextLine</option>
          <option value=".Word">Word</option>
          <option value=".Glyph">Glyph</option>
          <option value=".Group">Group</option>
          <option value=".Page[id], .TextRegion, .TextLine, .Word, .Glyph">Any</option>
        </select>
      </div>
      <div>
        Read direction:
        <label id="read-ltr"><input class="mousetrap" type="radio" name="read-dir" value="ltr" checked=""/> ltr</label>
        <label id="read-rtl"><input class="mousetrap" type="radio" name="read-dir" value="rtl"/> rtl</label>
        <label id="read-ttb"><input class="mousetrap" type="radio" name="read-dir" value="ttb"/> ttb</label>
      </div>
    </fieldset>
    <fieldset id="modifyElementsFieldset">
      <legend>Selected element modifications</legend>
      <div>
        Page rotation:
        <button id="rotateClockwise">↻</button>
        <button id="rotateAnticlockwise">↺</button>
      </div>
    </fieldset>
    <fieldset id="visibilityFieldset">
      <legend>Visibility</legend>
      <label id="hide-text-edit"><input class="mousetrap" type="checkbox" checked=""/> Lower pane</label>
      <label id="hide-img"><input class="mousetrap" type="checkbox" checked=""/> Image</label>
      <label id="hide-marker-start"><input class="mousetrap" type="checkbox"/> Coords/Baseline start markers</label>
      <div class="visibility-set">
        Region:
        <label id="hide-text-reg"><input class="mousetrap" type="checkbox"/> text</label>
        <label id="hide-poly-reg"><input class="mousetrap" type="checkbox"/> polygons</label>
        <label id="clip-text-reg"><input class="mousetrap" type="checkbox"/> text-clip</label>
      </div>
      <div class="visibility-set">
        Line:
        <label id="hide-text-line"><input class="mousetrap" type="checkbox"/> text</label>
        <label id="hide-poly-line"><input class="mousetrap" type="checkbox"/> polygons</label>
        <label id="hide-baselines"><input class="mousetrap" type="checkbox" checked=""/> baselines</label>
      </div>
      <div class="visibility-set">
        Word:
        <label id="hide-text-word"><input class="mousetrap" type="checkbox"/> text</label>
        <label id="hide-poly-word"><input class="mousetrap" type="checkbox"/> polygons</label>
      </div>
      <div class="visibility-set">
        Glyph:
        <label id="hide-text-glyph"><input class="mousetrap" type="checkbox"/> text</label>
        <label id="hide-poly-glyph"><input class="mousetrap" type="checkbox"/> polygons</label>
      </div>
      <div class="visibility-set">
        Other:
        <label id="hide-poly-other"><input class="mousetrap" type="checkbox"/> polygons</label>
      </div>
    </fieldset>
    <fieldset id="tabCycleFieldset">
      <legend>Tab selection</legend>
      <div>
        <label id="tab-sort"><input class="mousetrap" type="checkbox"/> Sort by:</label>
        <label id="tab-sort-text"><input class="mousetrap" type="radio" name="tab-sort-type" value="TextEquiv" checked=""/> Text conf</label>
        <label id="tab-sort-coords"><input class="mousetrap" type="radio" name="tab-sort-type" value="Coords"/> Coords conf</label>
      </div>
    </fieldset>
  </div>
  <div id="prop-modal" class="modal">
    <div class="modal-content">
      <span class="close">&#215;</span>
      <h2><span id="props-target"></span></h2>
      <h3>Properties</h3>
      <div id="props"></div>
      <h3 class="modal-textequiv-props">TextEquiv</h3>
      <div class="modal-textequiv-props"></div>
      <h3 class="modal-coords-props">Coords</h3>
      <div class="modal-coords-props"></div>
      <h3 class="modal-baseline-props">Baseline</h3>
      <div class="modal-baseline-props"></div>
    </div>
  </div>
  <div id="readme-modal" class="modal">
    <div class="modal-content markdown-body">
    </div>
  </div>
  <div id="spinner" class="modal">
    <div></div>
  </div>
</body>
</html>
