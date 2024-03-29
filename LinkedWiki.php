<?php
/**
 * @version 1.0.0.0
 * @package Bourdercloud/linkedwiki
 * @copyright (c) 2011 Bourdercloud.com
 * @author Karima Rafes <karima.rafes@bordercloud.com>
 * @link http://www.mediawiki.org/wiki/Extension:LinkedWiki
 * @license CC-by-nc-sa V3.0
 *
 * Last version : http://github.com/BorderCloud/LinkedWiki


	This work is licensed under the Creative Commons 
	Attribution-NonCommercial-ShareAlike 3.0 
	Unported License. To view a copy of this license, 
	visit http://creativecommons.org/licenses/by-nc-sa/3.0/ 
	or send a letter to Creative Commons,
	171 Second Street, Suite 300, San Francisco, 
	California, 94105, USA.
	
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	echo "This file is not a valid entry point.";
	exit( 1 );
}

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
   'name' => 'LinkedWiki',
   'version' => '2.0.0 alpha 1',
   'url' => 'http://www.mediawiki.org/wiki/Extension:LinkedWiki',
   'description' => 'See the Linked Data in your Wiki.',
   'author' => array( '[http://www.mediawiki.org/wiki/User:Karima_Rafes Karima Rafes]' )
);

//Paths
$wgLinkedWikiPath = dirname(__FILE__);
$wgLinkedWikiClassesPath = $wgLinkedWikiPath . "/class";
$wgLinkedWikiLibPath = $wgLinkedWikiPath . "/lib";
$wgLinkedWikiSpecialPagesPath = $wgLinkedWikiPath . "/specialpages";

//Libraries
require_once( $wgLinkedWikiLibPath ."/sparql/Endpoint.php");

//Classes
$wgAutoloadClasses['SpecialSparqlQuery'] = $wgLinkedWikiSpecialPagesPath . '/SpecialSparqlQuery.php';

//Install extension //TODO
$wgExtensionMessagesFiles['LinkedWiki'] = dirname( __FILE__ ) . '/LinkedWiki.i18n.php';

//Add special pages
$wgExtensionMessagesFiles['SpecialSparqlQuery'] = $wgLinkedWikiSpecialPagesPath . '/SpecialSparqlQuery.i18n.php';
$wgExtensionAliasesFiles['SpecialSparqlQuery'] = $wgLinkedWikiSpecialPagesPath . '/SpecialSparqlQuery.alias.php';
$wgSpecialPages['SpecialSparqlQuery']                     = array( 'SpecialSparqlQuery' );
$wgSpecialPageGroups['SpecialSparqlQuery']                = 'media';

//Add PARSER
# Define a setup function
$wgHooks['ParserFirstCallInit'][] = 'efSparqlParserFunction_Setup';
# Add a hook to initialise the magic word
$wgHooks['LanguageGetMagic'][]       = 'efSparqlParserFunction_Magic';

function efSparqlParserFunction_Setup( &$parser ) {
	$parser->setFunctionHook( 'sparql', 'efSparqlParserFunction_Render' );
	$parser->setFunctionHook( 'wsparql', 'efWsparqlParserFunction_Render' );
	$parser->setFunctionHook( 'properties', 'efPropertiesParserFunction_Render' );
	
	return true;
}

function efSparqlParserFunction_Magic( &$magicWords, $langCode ) {
	# Add the magic word
	# The first array element is whether to be case sensitive, in this case (0) it is not case sensitive, 1 would be sensitive
	# All remaining elements are synonyms for our parser function
	$magicWords['sparql'] = array( 0, 'sparql' );
	$magicWords['wsparql'] = array( 0, 'wsparql' );
	$magicWords['properties'] = array( 0, 'properties' );
	# unless we return true, other parser functions extensions won't get loaded.
	return true;
}

function fSparqlParserFunction_pageiri(&$parser) {
	$resolver = Title::makeTitle( NS_SPECIAL, 'URIResolver' );
	$resolverurl = $resolver->getFullURL() . '/';
	return SparqlTools::decodeURItoIRI($resolverurl).$parser->getTitle()->getPrefixedDBkey();
}

function efSparqlParserFunction_Render( $parser) {
	//global $wgLinkedWikiLocalEndPoint,$wgLinkedWikiEndPoint,$wgLinkedWikiGraphWiki;
	$args = func_get_args(); // $parser, $param1 = '', $param2 = ''
	$countArgs = count($args);
	$query = isset($args[1])? urldecode($args[1]) : "";
	$vars = array();
	for($i = 2;$i < $countArgs;$i++) {
		if(preg_match_all('#^([^= ]+) *= *(.*)$#i', $args[$i],$match)){
			$vars[$match[1][0]] = $match[2][0];
		}
	}
	
	if ($query != "") {
		
		$query  = efWsparqlParserFunction_parserquery($query,$parser);
		
		// which endpoint?
		$endpoint = isset($vars["endpoint"]) ? $vars["endpoint"] : 'http://dbpedia.org/sparql';
		$classHeaders = isset($vars["classHeaders"]) ? $vars["classHeaders"] :'';
		$headers = isset($vars["headers"]) ? $vars["headers"] :'';
		$templates = isset($vars["templates"]) ? $vars["templates"] :'';
		$debug = isset($vars["debug"]) ? $vars["debug"] :null;
		$cache = isset($vars["cache"]) ? $vars["cache"] :"yes";
		$templateBare = isset($vars["templateBare"]) ? $vars["templateBare"] :'';
		$footer = isset($vars["footer"]) ? $vars["footer"] :'';		
		
		
		if($cache == "no"){
			$parser->disableCache(); 
		}
		if($templateBare == "tableCell"){
			return efSparqlParserFunction_tableCell($query,$endpoint, $debug);
		}else{
			if($templates != ""){
				return efSparqlParserFunction_array($query,$endpoint,$classHeaders ,$headers , $templates,$footer , $debug);
			}else{
				return efSparqlParserFunction_simple($query,$endpoint,$classHeaders,$headers,$footer, $debug);
			}
		}
	}else {
		$parser->disableCache();
		return "'''Error #sparql : Argument incorrect (usage : #sparql: SELECT * WHERE {?a ?b ?c .} )'''";
	}
}

function efWsparqlParserFunction_Render( $parser) {
	$parser->disableCache(); //TODO OPTIMIZE
	
	//global $wgLinkedWikiLocalEndPoint,$wgLinkedWikiEndPoint,$wgLinkedWikiGraphWiki;
	$args = func_get_args(); // $parser, $param1 = '', $param2 = ''
	$countArgs = count($args);
	$query = "";
	$debug = null;
	$cache = "yes";
	$endpoint =  "http://dbpedia.org/sparql";
	$namewidget = isset($args[1])? $args[1] : "";
	$vars = array();
	for($i = 2;$i < $countArgs;$i++) {		
		// FIX bug : Newline breaks query
		if(preg_match_all('#^([^= ]+)=((.|\n)*)$#i', $args[$i],$match)){
			if($match[1][0] == "query"){
				$query = urldecode($match[2][0]);
			}elseif($match[1][0] == "debug"){
				$debug = $match[2][0];
			}elseif($match[1][0] == "endpoint"){
				$endpoint = $match[2][0];
			}elseif($match[1][0] == "cache"){
				$cache = $match[2][0];
			}else{
				$vars[] = $args[$i];
			}
		}else{
				$vars[] = $args[$i];
			}
	}
	
	if($cache == "no"){
			$parser->disableCache(); 
	}
		
	if ($query != "" && $namewidget != "" ) {			
		
		$query  = efWsparqlParserFunction_parserquery($query,$parser);
		
		return efSparqlParserFunction_widget($namewidget,$query,$endpoint,$debug,$vars);
	}else {
		$parser->disableCache(); 
	//TODO
	//	if($wgLinkedWikiEndPoint == null)
	//		return "'''Error #sparql : you need to configure the endpoint in the localsettings.php for example : $wgSparqlToolsStore = ARC2::getRemoteStore(array('remote_store_endpoint' => 'http://www.MyEndPoint.com/sparql',));'''";
	//
		return "'''Error #sparql : Argument incorrect (usage : #wsparql:namewidget|query=SELECT * WHERE {?a ?b ?c .} )'''";
	}
}

function efSparqlParserFunction_widget($namewidget, $querySparqlWiki,$endpoint , $debug,$vars){

	$specialC = array("&#39;");
	$replaceC = array("'");
	$querySparql  = str_replace($specialC ,$replaceC , $querySparqlWiki);

	$str = "";
	$sp = new Endpoint($endpoint);
	$rs = $sp->query($querySparqlWiki);
	$errs = $sp->getErrors();
	if ($errs) {
		$strerr = "";
		foreach ($errs as $err) {
			$strerr .= "'''Error #sparql :". $err ."'''<br/>";
		}
		return $strerr;
	}

	$res_rows = array();
	$str = "";
	$i = 0;

	$variables = $rs['result']['variables'];
	foreach ( $rs['result']['rows'] as $row) {
		$res_row = array();
		foreach ( $variables as $variable) {
			$res_row[] = "rows.".$i.".".$variable."=".$row[$variable];

		}
		$res_rows[] = implode(" | ",$res_row );
		$i++;
	}
	
	$str = "{{#widget:".$namewidget."|".implode(" | ",$vars )."|".implode(" | ",$res_rows )."}}";
	if ($debug != null ){
		$str .= "\n".print_r($vars, true);
		$str .= print_r($rs, true);
		return  array("<pre>".$str."</pre>",'noparse' => true, 'isHTML' => true);
	}

	return array($str, 'noparse' => false);
}

/*
 * $querySparql : query sparql
 * $templates : a list of templates, separated by ","
 * $headers : replacement of th values
 */
function efSparqlParserFunction_array(  $querySparqlWiki,$endpoint ,$classHeaders = '',$headers = '', $templates = '', $footer = '', $debug = null ) {
	$specialC = array("&#39;");
	$replaceC = array("'");
	$querySparql  = str_replace($specialC ,$replaceC , $querySparqlWiki);

	$str = "";
	$sp = new Endpoint($endpoint);
	$rs = $sp->query($querySparqlWiki);
	$errs = $sp->getErrors();
	if ($errs) {
		$strerr = "";
		foreach ($errs as $err) {
			$strerr .= "'''Error #sparql :". $err ."'''<br/>";
		}
		return $strerr;
	}
	$variables = $rs['result']['variables'];
	$TableFormatTemplates = explode(",",$templates);

	$lignegrise = false;
	$str = "{| class=\"wikitable sortable\" \n";
	if( $headers !='' ){
		$TableTitleHeaders = explode(",",$headers);
		$TableClassHeaders = explode(",",$classHeaders);
		$classStr = "";
		for ($i = 0; $i < count($TableClassHeaders) ; $i++) {
			if(!isset($TableClassHeaders[$i]) || $TableClassHeaders[$i] == "")
				$classStr = "";
			else
				$classStr =  $TableClassHeaders[$i] . "|";
			$TableTitleHeaders[$i] =  $classStr . $TableTitleHeaders[$i];
		}

		$str .= "|- \n";
		$str .= "!" . implode("!!",$TableTitleHeaders );
		$str .= "\n";
	}

	$arrayParameters = array();
	$rssStr = "";
	foreach ( $rs['result']['rows'] as $row) {
		$str .= "|- ";
		if($lignegrise)
		$str .= "bgcolor=\"#f5f5f5\"";
		$lignegrise = !$lignegrise;
		$str .= "\n";
		$separateur = "|";
		unset($arrayParameters);
		foreach ( $variables as $variable) {
			if($row[$variable." type"] == "uri" ){
				$arrayParameters[] = $variable." = ". efSparqlParserFunction_uri2Link($row[$variable],true) ;
			}else {
				$arrayParameters[] = $variable." = ". $row[$variable] ;
			}
		}
		foreach ( $TableFormatTemplates as $TableFormatTemplate) {
			$str .= $separateur  . "{{".$TableFormatTemplate."|".implode ( "|", $arrayParameters)."}}";
			$separateur = "||";
		}
		$str .= "\n";
	}	
	
	if($footer != "NO"){
		$str .= "|- style=\"font-size:80%\" align=\"right\"\n";
		$str .= "| colspan=\"".count($TableFormatTemplates )."\"|". efSparqlParserFunction_footer($rs['query_time'])."\n";
	}
	
	$str .= "|}\n";

	if ($debug != null &&  $debug == "YES"){
		$str .= "INPUT WIKI : ".$querySparqlWiki."\n";
		$str .= "Query : ".$querySparql."\n";
		$str .= print_r($arrayParameters, true);
		$str .= print_r($rs, true);
		return  array("<pre>".$str."</pre>",'noparse' => true, 'isHTML' => true);
	}

	return array($str, 'noparse' => false, 'isHTML' => false);
}

function efSparqlParserFunction_simple( $querySparqlWiki,$endpoint ,$classHeaders = '',$headers = '',$footer = '', $debug = null){
	$specialC = array("&#39;");
	$replaceC = array("'");
	$querySparql  = str_replace($specialC ,$replaceC , $querySparqlWiki);

	$str = "";
	$sp = new Endpoint($endpoint);
	$rs = $sp->query($querySparqlWiki);
	$errs = $sp->getErrors();
	if ($errs) {
		$strerr = "";
		foreach ($errs as $err) {
			$strerr .= "'''Error #sparql :". $err ."'''<br/>";
		}
		return $strerr;
	}

	$lignegrise = false;
	$variables = $rs['result']['variables'];
	$str = "{| class=\"wikitable sortable\" \n";
	if( $headers !='' ){
		$TableTitleHeaders = explode(",",$headers);
		$TableClassHeaders = explode(",",$classHeaders);
		$classStr = "";
		for ($i = 0; $i < count($TableClassHeaders) ; $i++) {
			if(!isset($TableClassHeaders[$i]) || $TableClassHeaders[$i] == ""){
				$classStr = "";
			}else{
				$classStr =  $TableClassHeaders[$i] . "|";
			}
			$TableTitleHeaders[$i] =  $classStr . $TableTitleHeaders[$i];
		}
		$str .= "|- \n";
		$str .= "!" . implode("!!",$TableTitleHeaders );
		$str .= "\n";
	}else{
		$TableClassHeaders = explode(",",$classHeaders);
		$classStr = "";
		for ($i = 0; $i < count($variables) ; $i++) {
			if(!isset($TableClassHeaders[$i]) || $TableClassHeaders[$i] == "")
			$classStr = "";
			else
			$classStr =  $TableClassHeaders[$i] . "|";
			$variables[$i] =  $classStr . $variables[$i];
		}

		$str .= "|- \n";
		$str .= "!" . implode("!!",$variables );
		$str .= "\n";
	}
	foreach ( $rs['result']['rows'] as $row) {
		$str .= "|- ";
		if($lignegrise)
		$str .= "bgcolor=\"#f5f5f5\"";
		$lignegrise = !$lignegrise;
		$str .= "\n";
		$separateur = "|";
		foreach ( $variables as $variable) {
			if($row[$variable." type"] == "uri" ){
				$str .= $separateur .  efSparqlParserFunction_uri2Link($row[$variable]) ;
			}else{
				$str .= $separateur  . $row[$variable] ;
			}
			$separateur = "||";
		}
		$str .= "\n";
	}

	if($footer != "NO"){
		$str .= "|- style=\"font-size:80%\" align=\"right\"\n";
		$str .= "| colspan=\"".count($variables)."\"|". efSparqlParserFunction_footer($rs['query_time'])."\n";
	}
	
	$str .= "|}\n";

	if ($debug != null  &&  $debug == "YES"){
		$str .= "INPUT WIKI : ".$querySparqlWiki."\n";
		$str .= "Query : ".$querySparql."\n";
		$str .= print_r($rs, true);
		$str .= print_r($rs, true);
		return  array("<pre>".$str."</pre>",'noparse' => true, 'isHTML' => false);
	}

	return array($str, 'noparse' => false, 'isHTML' => false);
}

function efSparqlParserFunction_tableCell( $querySparqlWiki,$endpoint ,$debug = null){
	$specialC = array("&#39;");
	$replaceC = array("'");
	$querySparql  = str_replace($specialC ,$replaceC , $querySparqlWiki);

	$str = "";
	$sp = new Endpoint($endpoint);
	$rs = $sp->query($querySparqlWiki);
	$errs = $sp->getErrors();
	if ($errs) {
		$strerr = "";
		foreach ($errs as $err) {
			$strerr .= "'''Error #sparql :". $err ."'''<br/>";
		}
		return $strerr;
	}

	$lignegrise = false;
	$variables = $rs['result']['variables'];
	$str = "";
	foreach ( $rs['result']['rows'] as $row) {
		$str .= "\n";
		$separateur = "| ";
		foreach ( $variables as $variable) {
			if($row[$variable." type"] == "uri" ){
				$str .= $separateur .  efSparqlParserFunction_uri2Link($row[$variable]) ;
			}else{
				$str .= $separateur  . $row[$variable] ;
			}
			$separateur = " || ";
		}
		$str .= "\n|- \n";
	}

	if ($debug != null  &&  $debug == "YES"){
		$str .= "INPUT WIKI : ".$querySparqlWiki."\n";
		$str .= "Query : ".$querySparql."\n";
		$str .= print_r($rs, true);
		$str .= print_r($rs, true);
		return  array("<pre>".$str."</pre>",'noparse' => true, 'isHTML' => false);
	}

	return array($str, 'noparse' => false, 'isHTML' => false);
}

function  efSparqlParserFunction_footer($duration){
	$today = date(wfMsg('date'));
	return $today ." -- [{{fullurl:{{FULLPAGENAME}}|action=purge}} ".wfMsg('refresh')."] -- ".wfMsg('durate')." :". round($duration, 3) ."s";
}

function  efSparqlParserFunction_uri2Link($uri,$nowiki = false){
	//TODO : $title ??? CLEAN ?
	global $wgServer;
	$result = "";
	//$fromPatternThisWiki = "#^". str_replace( '.', '\.', $wgServer).".*:URIResolver/(.*)$#i";
	$fromPatternThisWiki = "#^". str_replace( '.', '\.', $wgServer).".*:URIResolver/(?:(.*):(.*)|(.*))$#i";
	$titleObj = null;
	$title = "";
	$forCategory = ""; 
	$isKnow = true;
	if(preg_match_all($fromPatternThisWiki,$uri,$match)) {
		$uri = SMWExporter::decodeURI( $uri );
		$uri = str_replace( "_", "%20", $uri );
		$uri = urldecode( $uri );
		preg_match_all($fromPatternThisWiki,$uri,$match);
		if( $match[1][0] == ''){	//no namespace
			$titleObj = Title::newFromText( $match[3][0]);
			$title  = $match[3][0];
		}else{
			global $wgContLang;
			$ns = $wgContLang->getNsIndex($match[1][0]);
			if(!$ns)
			$isKnow = false;
			else{
				$titleObj = Title::newFromText($match[2][0],$ns);
				$title  = $match[2][0];
				if($ns == NS_CATEGORY){
					$forCategory = ":";
				}
			}
		}

	}else{
		$isKnow = false;
	}

	if($isKnow){
		if($nowiki ){
			if($titleObj != null)
				$result =   $titleObj->getText();
			else
				$result = $title;
		}else{
			if($titleObj != null)
				$result =   "[[".$forCategory.$titleObj->getPrefixedDBkey()."|".$titleObj->getText() ."]]";
			else
				$result =  "[[".$forCategory.$title."]]";
		}
	}else{
		$result =  str_replace("=","{{equal}}",$uri);
	}
	return $result;
}

function efWsparqlParserFunction_parserquery($query,$parser) {	
	//global $wgLinkedWikiGraphWiki;
	
	$res = $query;
// 	if (preg_match("/<PAGEIRI>/i",$res)) {
// 			$uri  = "<".fSparqlParserFunction_pageiri($parser).">";
// 		   $res  = str_replace("<PAGEIRI>",$uri , $res);			
// 	}
// 	if (preg_match("/<WIKIGRAPH>/i",$res)) {
// 			$uri  = "<".$wgLinkedWikiGraphWiki.">";
// 		   $res  = str_replace("<WIKIGRAPH>",$uri , $res);	
// 	}
	return $res;
}

function efPropertiesParserFunction_Render( &$parser, $propertyName = '', $list = '' ) {
	$outStr = "";
	$arraylist;
	$strComma = "";
	if($propertyName != '' && $list != '' ){
		$property = trim($propertyName);
		$arraylist = explode( "," , $list );
		foreach ($arraylist as $value) {
			$v = trim($value);
		    $outStr .= $strComma."[[".$property."::".$v."|".$v."]]";
		    $strComma = ", ";
		}
		unset($v); 
	}
    return $outStr;
}
