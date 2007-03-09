<?php if(!function_exists('startedIndexPhp')) { header("location:../index.php"); exit();}
# streber - a php5 based project management system  (c) 2005-2007  / www.streber-pm.org
# Distributed under the terms and conditions of the GPL as stated in lang/license.html

/**
 * classes related to miscellenious string formatting and rendering
 *
 * included from: render_page.inc
 *
 *
 * @author Thomas Mann
 * @uses:
 * @usedby: throughout everywhere
 *
 */





/**
* get theme-directory (without slashes) from current user-definition
*
* if undefined, return default-theme
*/
function getCurTheme()
{
    global $auth;
    global $PH;
    global $g_theme_names;

    ### make sure theme is define ###
    if(isset($auth)
     && isset($auth->cur_user)
     && isset($g_theme_names[$auth->cur_user->theme])
     && ($theme= $g_theme_names[$auth->cur_user->theme])) {
        return $theme;
    }

    return $g_theme_names[confGet('THEME_DEFAULT')];
}



/**
* get url to file from the current theme
*
* Used to access files from a theme. Example:
*
*  echo '<img src="'. getThemeFile("/img/prio_{$obj->prio}.png") . '">';
*
*
* - if file does not exists, returns path to default theme
* - if file does not exists there a warning is been triggered

*/
function getThemeFile($filepath)
{
    $theme= getCurTheme();
    $path= "themes/".getCurTheme()."/".$filepath;
    if(file_exists($path)) {
        return $path;
    }

    ### @@@pixtur:2006-10-11 using clean is not very good. Better would be default theme.
    $path= "themes/clean/". $filepath;
    if(file_exists($path)) {
        return $path;
    }
    else {
        trigger_error("unknown theme file '". $filepath. "'", E_USER_WARNING);
        return "";
    }
}



/**
 * Exception thrown related to rendering
 *
 * Prints message and some debug-output.
*/
class RenderException extends Exception
{
  public $backtrace=NULL;

  function __construct($message=false, $code=false)
  {
	$this->message="";
    if($message) {
        $this->message="<pre>$message";
    }
    $this->backtrace = debug_backtrace();
  }
}

/**
 * convert url to external link-tag (remove http:/ and reduced to reasonable length)
 *
 * add http:/ if missing
 */
function url2linkExtern($url, $show=NULL, $maxlen=20) {
    if(!preg_match("/^http:\/\//",$url)) {
        if(!$show) {
            $show= $url;
        }
        $url="http://$url";
    }
    else {
        if(!$show) {
            $show= preg_replace("/^https?:\/\//","",$url);
        }
    }
    if(strlen($show) > $maxlen) {
        $show=substr($show,0,$maxlen)."...";
    }
    return "<a target='_blank' class='extern' href='". asHtml($url) ."'>" . asHtml($show) ."</a>";
}

/**
 * convert url to mail link-tag (remove mail:// and reduced to reasonable length)
*/
function url2linkMail($url,$show=false, $maxlen=32) {

    $url= asHtml($url);
    if(!preg_match("/^mailto:/",$url)) {
        if(!$show) {
            $show= $url;
        }
        $url="mailto:$url";
    }
    else {
        if(!$show) {
            $show= preg_replace("/^mailto?:/","",$url);
        }
    }

    if(strlen($show) > $maxlen) {
        $show=substr($show,0,$maxlen)."...";
    }
    return "<a class='mail' href='".asHtml($url). "'>". asHtml($show)."</a>";
}










/**
* NOTE: rolling out task-crumbreads at the top navigation is no longer supported
* Since v0.06 the prefered method is listing the breadcrumbs before the page-title
*/
function build_task_crumbs(&$task, &$project=NULL) {
    $crumbs=array();


    if(!$project) {
        $project= Project::getVisibleById($task->project);
    }

    if($project) {

    	$crumbs=array(
    	    new NaviCrumb(array('target_id'=>'projList')),
    	    new NaviCrumb(array(
	            'target_id'     =>'projView',
	            'target_params' => array('prj'=>$task->project),
	            'name'          => $project->getShort(),
	            'tooltip'       => $project->name,
    	    )),
    	    new NaviCrumb(array(
	            'target_id'     =>'projViewTasks',
	            'target_params' => array('prj'=>$task->project),
    	    )),
    	);

        ### breadcrumb + folders ##
        $folders= $task->getFolder();
        foreach($folders as $f) {
            $crumbs[]=
                    new NaviCrumb(array(
                        'target_id'     =>'taskView',
                        'target_params' =>array('tsk'=>$f->id),
                        'name'          =>$f->getShort(),
                        'type'          =>'task',
                    ));
        }
    }

    $crumbs[]=
    new NaviCrumb(array(
        'target_id'     =>'taskView',
        'target_params' =>array('tsk'=>$task->id),
        'name'          =>$task->getShort(),
    ));
    return $crumbs;

}

function build_person_crumbs(&$person) {
    $crumbs=array();

	$crumbs[]= new NaviCrumb(array(
		'target_id'     =>'personList',
		'name'          =>__('Other Persons','page option'),
	));
    
    $crumbs[]= new NaviCrumb(array(
        'target_id'     => 'personView',
        'target_params' => array('person'=> $person->id),
        'name'          => $person->name,
        'type'=>'person',
  	));
  	return $crumbs;
}

function build_company_crumbs(&$company) {
    $crumbs=array();

	$crumbs[]= new NaviCrumb(array(
		'target_id'     => 'companyList',
	));

    $crumbs[]= new NaviCrumb(array(
            'target_id'     => 'companyView',
            'name'          => $company->name,
            'target_params' => array('company'=>$company->id),
    ));

  	return $crumbs;
}


function build_project_crumbs($project) {
    $a=array();

    ### breadcrumbs (distinguish active/closed projects ###
	/*if($project->status > 3) {
        $a[]=
    	    new NaviCrumb(array(
	            'target_id'=>'projListClosed',
    	    ));
    }
    else if($project->status == -1) {
        $a[]=
    	    new NaviCrumb(array(
	            'target_id'=>'projListTemplates',
    	    ));
    }
    else {
        $a[]=
    	    new NaviCrumb(array(
	            'target_id'=>'projList',
    	    ));
    }
    */


    $a[]= new NaviCrumb(array(
            'target_id'=>'projView',
            'name'=>    $project->getShort(),
            'tooltip'=> $project->name,
            'target_params'=>array('prj'=>$project->id ),
            'type'=>'project',
        ));

    return $a;
}

/**
* renders the list of open projects that will be display when opening the project selector
*
* The opening is done with javascript. Placing the list beside the Project Selector icon
* is done by css only. This is a little bit tricky, because the Tab-list is already an
* span which allows only further Spans to be included...
*
* read more at #3867
*/
function buildProjectSelector()
{

    global $auth;
    if(!$auth->cur_user || !$auth->cur_user->id) {
        return "";
    }
    $buffer= "";

    global $PH;

    require_once(confGet('DIR_STREBER') . "db/class_project.inc.php");

    if($projects= Project::getAll(array(
    ))) {
        $buffer.="<span id=projectselector>&nbsp;</span>";
        $buffer.= "<span style='display:none;' id='projectselectorlist'>";

        foreach($projects as $p) {
            $buffer.= $PH->getLink('projView',$p->name, array('prj' => $p->id));
        }
        $buffer.="</span>";
    }
    return $buffer;
}



/**
* build the navigation-options for project view
* Note: for project_breadcrumps see render/render_misc
*/
function build_projView_options($project)
{
    $options=array();
    $options[]=  new NaviOption(array(
            'target_id'=>'projViewTasks',
            'name'=>__('Tasks','Project option'),
            'target_params'=>array('prj'=>$project->id )
    ));

    $options[]=  new NaviOption(array(
            'target_id'=>'projViewDocu',
            'name'=>__('Docu','Project option'),
            'target_params'=>array('prj'=>$project->id )
    ));

    if($project->settings & PROJECT_SETTING_MILESTONES) {
        $options[]=  new NaviOption(array(
            'target_id'=>'projViewMilestones',
            'name'=>__('Milestones','Project option'),
            'target_params'=>array('prj'=>$project->id )
        ));
    }

    if($project->settings & PROJECT_SETTING_VERSIONS) {
        $options[]=  new NaviOption(array(
                'target_id'=>'projViewVersions',
                'name'=>__('Releases','Project option'),
                'target_params'=>array('prj'=>$project->id )
        ));
    }



    $options[]=new NaviOption(array(
        'target_id'=>'projViewFiles',
        'name'=>__('Files','Project option'),
        'target_params'=>array('prj'=>$project->id )
    ));

    if($project->settings & PROJECT_SETTING_EFFORTS) {
        $options[]=  new NaviOption(array(
                'target_id'=>'projViewEfforts',
                'name'=>__('Efforts','Project option'),
                'target_params'=>array('prj'=>$project->id )
        ));
    }
    $options[]=  new NaviOption(array(
            'target_id'=>'projViewChanges',
            'name'=>__('History','Project option'),
            'target_params'=>array('prj'=>$project->id )
    ));
    return $options;
}


function build_personList_options()
{
    return array(
        new NaviOption(array(
            'target_id'=>'personList',
            'name'=>__('Persons', 'page option')
        ))
    );
}

function build_companyList_options()
{
    return array(
        new NaviOption(array(
            'target_id'=>'companyList',
            'name'=>__('Companies', 'page option')
        )),
    );
}






function build_projList_options()
{
    return array(
        new NaviOption(array(
            'target_id'=>'projList',
            'name'=>__('Active')
        )),
        new NaviOption(array(
            'target_id'=>'projListClosed',
            'name'=>__('Closed')
        )),
        new NaviOption(array(
            'target_id'=>'projListTemplates',
            'name'=>__('Templates'),
            'separated'=> true,
        )),
    );
}






/**
* actually this function is obsolete since all error-related debug-output should
* be generated by trigger_error (which has it's own backtrace-function)
*/
function renderBacktrace($arr)
{
    $buffer='';

    ### ignore empty array ###
    if(!count($arr)) {
        return false;
    }

    $buffer.= "<table class=backtrace>";

    ### write header ###
    $buffer.="<tr>";
    foreach($arr[0] as $key=>$value) {
        $buffer.="<th>$key</th>";
    }

    $buffer.="</tr>";

    ### write lines ###
    foreach($arr as $n) {
        $buffer.="<tr>";
        foreach($n as $key=>$value) {
            if(is_array($value)) {
                $buffer.='<td>';
                foreach($value as $no) {
                    if(is_object($no)) {
                        $buffer.=get_class($no);
                    }
                    else {
                        $buffer.=$no;
                    }
                    $buffer.=".<br>";
                }
                $buffer.="</td>";
            }
            else if(is_object($value)) {
                $buffer.='<td>';
                #$buffer.=join("##<br>",$value);
                $buffer.="</td>";
            }
            else {
                $value= str_replace("c:\\programme\\Apache13\\Apache\\htdocs\\nod\\","",$value);
                $buffer.="<td>$value</td>";
            }
        }
        $buffer.="</tr>";
    }
    $buffer.= "</table>";
    return $buffer;
}







/**
* wrapper functions for formatted time output
* cache strings to avoid too many access to the language tables and
* to attempt to fix portability problems with strftime
*/
function getUserFormatDate()
{
    global $userFormatDate;
    if(!$userFormatDate)
    {
        $userFormatDate = __('%b %e, %Y', 'strftime format string');

        // fix %e formatter if not supported (e.g. on Windows)
        if(strftime("%e", mktime(12, 0, 0, 1, 1)) != '1')
            $userFormatDate = str_replace("%e", "%d", $userFormatDate);
    }
    return $userFormatDate;
}

function getUserFormatTime()
{
    global $userFormatTime;
    if($userFormatTime)
        $userFormatTime = __('%I:%M%P', 'strftime format string');
    return $userFormatTime;
}

function getUserFormatTimestamp()
{
    global $userFormatTimestamp;
    if(!$userFormatTimestamp)
    {
        $userFormatTimestamp = __('%a %b %e, %Y %I:%M%P', 'strftime format string');

        // fix %e formatter if not supported (e.g. on Windows)
        if(strftime("%e", mktime(12, 0, 0, 1, 1)) != '1')
            $userFormatTimestamp = str_replace("%e", "%d", $userFormatTimestamp);
    }
    return $userFormatTimestamp;
}




/**
* NOTE:
* - all time RENDER functions will ADD user's Time offset to render time in client time
* - vize versa all parseTime functions (see render/render_fields.inc.php) will SUBSTRACT the time offset
*   before storing anything to the DB
*/
function renderTimestamp($t)
{

    if(!$t || $t=="0000-00-00 00:00:00") {
        return "";
    }
    if(is_string($t)) {
        $t= strToClientTime($t);
    }

    ### omit time with exactly midnight
    if(gmdate("H:i:s",$t)=="00:00:00") {
        $str= gmstrftime(getUserFormatDate(), $t);
    }
    else {
        $str= gmstrftime(getUserFormatTimestamp(), $t);
    }
    return $str;
}



function renderTimestampHtml($t)
{
    if(!$str= renderTimestamp($t)) {
        return "-";
    }

    if(is_string($t)) {
        $t= strToClientTime($t);
    }



/*    ### hilight new dates ###
    if(isset($auth) && isset($auth->cur_user) && gmdate("Y-m-d H:i:s",$t) > $auth->cur_user->last_logout) {
        $str_tooltip= __("new since last logout");
        return "<span class=new title='$str_tooltip'>$str</span>";
    }
    else {
    */
        return $str;


}



function renderTime($t)
{
    if(!$t  || $t=="0000-00-00 00:00:00") {
        return "";
    }
    if(is_string($t)) {
        $t= strToClientTime($t);
    }
    return gmstrftime(getUserFormatTime(), $t);
}



function renderDuration($t)
{
    if(!$t  || $t=="0000-00-00 00:00:00") {
        return "";
    }

    if($t > confGet('WORKDAYS_PER_WEEK') * confGet('WORKHOURS_PER_DAY') * 60 * 60) {
        return sprintf(__('%s weeks'), floor($t / (confGet('WORKDAYS_PER_WEEK') * confGet('WORKHOURS_PER_DAY') * 60 * 60)));
    }
    else if($t > confGet('WORKHOURS_PER_DAY') * 60 * 60) {
        return sprintf(__('%s days'), floor($t / (confGet('WORKHOURS_PER_DAY') * 60 * 60)));
    }
    else if($t > 60 * 60) {
        return sprintf(__('%s hours'), floor($t / (60 * 60)));
    }
    else {
        return sprintf(__('%s min'), floor($t / 60));
    }
}


/**
* expects GMT times!
*/
function renderDate($t, $smartnames= true) {
    if(!$t || $t=="0000-00-00 00:00:00" || $t=="0000-00-00") {
        return "";
    }

    if(is_string($t)) {

        ### do not offset simple dates ###
        if(preg_match("/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/", $t)) {
            $t= strToClientTime($t);
        }
        else {
            $t= strToGMTime($t);
        }
    }
    else {
        global $auth;
        $time_offset= 0;
        if(isset($auth->cur_user)) {
            $time_offset = $auth->cur_user->time_offset;
        }
        $t= $time_offset;
    }


    if($smartnames && gmdate('Y-m-d', GMTToClientTime(time())) == gmdate('Y-m-d', GMTToClientTime($t))) {
        $str= __('Today');
        if(gmdate('H:i:s',$t) !== '00:00:00') {
            $str.= ' ' . gmstrftime(getUserFormatTime(), $t);
        }
    }
    else if($smartnames && gmdate('Y-m-d', GMTToClientTime(time())) == gmdate('Y-m-d', GMTToClientTime($t + 60*60*24))) {
        $str= __('Yesterday');
        if(gmdate('H:i:s',$t) !== '00:00:00') {
            $str.= ' ' . gmstrftime(getUserFormatTime(), $t);
        }
    }
    else {
        $str= gmstrftime(getUserFormatDate(), $t);
    }
    return $str;

}

function renderDateHtml($t)
{
    global $auth;


    ### this is the visible string ###
    if(!$str= renderDate($t)) {
        return "-";
    }

    ### this is for the tooltip ###
    if(is_string($t)) {
        $t= strToClientTime($t);
    }
    else {
        global $auth;
        $time_offset= 0;
        if(isset($auth->cur_user)) {
            $time_offset = $auth->cur_user->time_offset;
        }
        $t+= $time_offset;
    }




    ### tooltip ? ###
    $str_tooltip='';
    if(gmdate('H:i:s',$t) != '00:00:00') {
        $str_tooltip= gmstrftime(getUserFormatTimestamp(), $t);
    }

    ### hilight new dates ###
    /*
    if(isset($auth) && isset($auth->cur_user) && getGMTString($t) > $auth->cur_user->last_logout) {

        $str_tooltip .= " ". __("new since last logout");
        return "<span class='new date' title='$str_tooltip'>$str</span>";
    }
    */

    if($str_tooltip){
        return "<span class='date' title='$str_tooltip'>$str</span>";
    }
    else {
        return $str;
    }

}


/**
* want duration in seconds
*/
function renderEstimatedDuration($duration)
{
    $duration /=  (60 * 60);

    $hours_per_day= confGet('WORKHOURS_PER_DAY');
    $days_per_week= confGet('WORKDAYS_PER_WEEK');

    if(!$duration) {
        return '';
    }

    if($duration <= $hours_per_day) {
        $type= 'hours';
        $str= sprintf(__('%s hours'), $duration);
    }
    else if($duration < $hours_per_day * $days_per_week) {
        $type= 'days';
        $str= sprintf(__('%s days'), $duration / $hours_per_day);
    }
    else {
        $type= 'weeks';
        $str= sprintf(__('%s weeks'), $duration / $hours_per_day / $days_per_week);
    }
    return $str;
}

/**
* renders a html-estimation / completion graph
*
* @used_by: list_tasks, list_milestones
*/
function renderEstimationGraph($estimated, $estimated_max, $completion)
{
    $str= '';
    #if(preg_match("/(\d\d):(\d\d):(\d\d)/", $obj->estimated, $matches)) {
    #    $estimated= ($matches[1]*60*60 + $matches[2]*60 + $matches[3]) / 60;
    if($estimated) {
        $estimated= $estimated/60/60;
        $estimated_max= $estimated_max/60/60;


        $hours_per_day= confGet('WORKHOURS_PER_DAY');
        $days_per_week= confGet('WORKDAYS_PER_WEEK');


        if($estimated_max <= $hours_per_day*2) {
            $type= 'hours';
            $width_estimated= $estimated * 4;
            $width_estimated_max= $estimated_max * 4;
            $str_estimated= sprintf(__('estimated %s hours'), $estimated);
            $str_estimated_max= $estimated_max
                              ? '('. sprintf(__('%s hours max'), $estimated_max) .')'
                              : '';
        }
        else if($estimated_max < $hours_per_day * $days_per_week*2) {
            $type= 'days';
            $width_estimated= $estimated / $hours_per_day * 6;
            $width_estimated_max= $estimated_max / $hours_per_day * 6;
            $str_estimated= sprintf(__('estimated %s days'), $estimated / $hours_per_day);

            $str_estimated_max= $estimated_max
                              ? '('. sprintf(__('%s days max'), $estimated_max / $hours_per_day) .')'
                              : '';

        }
        else {
            $type= 'weeks';
            $width_estimated= $estimated / $hours_per_day / $days_per_week * 12;
            $width_estimated_max= $estimated_max / $hours_per_day / $days_per_week * 12;

            $str_estimated= sprintf(__('estimated %s weeks'), $estimated / $hours_per_day / $days_per_week);
            $str_estimated_max  = $estimated_max
                                ? '('. sprintf(__('%s weeks max'), $estimated_max / $hours_per_day / $days_per_week) .')'
                                : '';
        }

        $str_completion= sprintf(__("%2.0f%% completed"), $completion);
        $html_tooltip= "title='". $str_estimated . " ". $str_estimated_max." / " . $str_completion. "'";

        if($completion) {
            $width_completion= $completion / 100 * $width_estimated;

            $html_completion= "<div  class='estimated {$type}_completed' style='width:{$width_completion}px;'></div>";
        }
        else {
            $html_completion='';
        }

        if($width_estimated_max > $width_estimated) {
            $width_risk= floor($width_estimated_max - $width_estimated)-1;
            $html_risk= "<div class='{$type}_risk'  style='width:{$width_risk}px;'></div>";
        }
        else {
            $html_risk='';
        }


        $str= "<div $html_tooltip class='estimated {$type}' style='width:{$width_estimated_max}px;'>"
            . $html_completion
            . $html_risk
            . "</div>";
    }
    return $str;
}

/**
* renders a date suitable for page titles/subtitles
* e.g.: Monday, 1st
*/
function renderTitleDate($t)
{
    /*
            As far as I (ganesh) know, the strftime %e format is good enough for all languages except English.
            For English we use the S format of function date() to get ordinals, which has no strftime equivalent.
        */
    global $g_lang;
    if($g_lang == 'en')
        $str = date('l, F jS', $t);
    else
        $str = strftime(__('%A, %B %e', 'strftime format string'), $t);
    return $str;
}



/**
* @@@ move this somewhere else...
*/
function render_changes($text_org,$text_new)
{
    require_once(confGet('DIR_STREBER') . "std/difference_engine.inc.php");

    $buffer= '';

	$ota = explode( "\n", str_replace( "\r\n", "\n", $text_org ) );
	$nta = explode( "\n", str_replace( "\r\n", "\n", $text_new ) );
	$diffs = new Diff( $ota, $nta );

    $debug='';

    $buffer.="<table class=diff>";

	foreach($diffs as $d) {
	    $buffer.="<tr>";
	    $buffer.="<td class=changeblock colspan=2></td></tr><tr>";
	    foreach($d as $do) {

		    if($do->type == 'add') {
		        $buffer.="<tr>";
		        $buffer.="<td class=neutral></td>";
		        $buffer.="<td class=add>". arrayAsHtml($do->closing). "</td>";
		        $buffer.="</tr>";

		    }
		    else if($do->type =='delete') {
		        $buffer.="<tr>";
		        $buffer.="<td class=deleted>". arrayAsHtml($do->orig). "</td>";
		        $buffer.="<td class=neutral></td>";
		        $buffer.="</tr>";

		    }
		    else if($do->type =='change') {
		        $wld= new WordLevelDiff($do->orig, $do->closing);
		        $buffer_org ='';
		        $buffer_new ='';
		        foreach($wld->edits as $e) {
		            switch($e->type) {
		                case 'copy':
		                    $orig= implode('',$e->orig);
		                    $buffer_org.= asHtml($orig);
		                    $buffer_new.= asHtml($orig);
		                    break;

		                case 'add':
		                    $buffer_org.= '<span class=add_place> </span>';
		                    $closing=implode('',$e->closing);
		                    $buffer_new.= '<span class=add>'.asHtml($closing).'</span>';
		                    break;

		                case 'change':
		                    $orig= implode('',$e->orig);
		                    $closing= implode('',$e->closing);
		                    $buffer_org.= '<span class=changed>'.asHtml($orig).'</span>';
		                    $buffer_new.= '<span class=changed>'.asHtml($closing).'</span>';
		                    break;

		                case 'delete':
		                    $orig= implode('',$e->orig);

		                    $buffer_org.= '<span class=deleted>'.asHtml($orig).'</span>';
		                    $buffer_new.= '<span class=delete_place> </span>';
		                    break;

		                default:
		                    trigger_error("undefined edit work edit", E_USER_WARNING);
		                    break;

		            }
		        }
	            $buffer_org= str_replace("\n", '<br>', $buffer_org);
	            $buffer_new= str_replace("\n", '<br>', $buffer_new);

		        $buffer.="<tr>";
		        $buffer.="<td class='changed'>". $buffer_org. "</td>";
		        $buffer.="<td class='changed'>". $buffer_new. "</td>";
		        $buffer.="</tr>";

		    }
		    else if($do->type == 'copy') {
		        $buffer.="<tr>";
		        $buffer.="<td class='copy'>". arrayAsHtml($do->orig). "</td>";
		        $buffer.="<td class='copy'>". arrayAsHtml($do->closing). "</td>";
		        $buffer.="</tr>";

		    }
		    else {
		        trigger_error("unknown diff type");
		    }
		}
	   $buffer.="</tr>";

	}
	$buffer.="</table>";

    return $buffer;
}

/**
* implodes an array of strings into save html output
*
* - used for rendering differences
*/
function &arrayAsHtml($strings)
{
    $buffer = '';
    $sep    = '';
    foreach($strings as $s) {
        $buffer.= $sep.asHtml($s);
        $sep = '<br>';
    }
    $buffer= str_replace("  ", "  ", $buffer);
    return $buffer;
}
?>