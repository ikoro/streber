<?php if(!function_exists('startedIndexPhp')) { header("location:../index.php"); exit();}
# streber - a php5 based project management system  (c) 2005-2007  / www.streber-pm.org
# Distributed under the terms and conditions of the GPL as stated in lang/license.html

/**\file   pages for working with tasks */

require_once(confGet('DIR_STREBER') . 'db/class_issue.inc.php');
require_once(confGet('DIR_STREBER') . 'db/class_task.inc.php');
require_once(confGet('DIR_STREBER') . 'db/class_project.inc.php');
require_once(confGet('DIR_STREBER') . 'render/render_list.inc.php');
require_once(confGet('DIR_STREBER') . 'lists/list_taskfolders.inc.php');
require_once(confGet('DIR_STREBER') . 'lists/list_comments.inc.php');
require_once(confGet('DIR_STREBER') . 'lists/list_tasks.inc.php');
require_once(confGet('DIR_STREBER') . 'db/class_taskperson.inc.php');
require_once(confGet('DIR_STREBER') . 'db/class_effort.inc.php');
require_once(confGet('DIR_STREBER') . 'db/class_person.inc.php');
require_once(confGet('DIR_STREBER') . 'db/db_itemperson.inc.php');


/**
* Create a task as bug
*
* @ingroup pages
*/
function taskNewBug()
{
    $foo=array(
        'add_issue'=>1,
        'task_category' =>TCATEGORY_BUG
        );
    addRequestVars($foo);
    TaskNew();
    exit();
}

/**
* Create a task as docu page
*
* @ingroup pages
*/
function taskNewDocu()
{
    $foo=array(
        'task_category' =>TCATEGORY_DOCU,
        'show_folder_as_documentation' => 1,
        );
    addRequestVars($foo);
    TaskNew();
    exit();
}


/**
* Create a new milestone
*
* @ingroup pages
*/
function TaskNewMilestone()
{
    global $PH;

    $prj_id=getOnePassedId('prj','',true,__('No project selected?')); # aborts with error if not found
    if(!$project= Project::getVisibleById($prj_id)) {
        $PH->abortWarning("invalid project-id",ERROR_FATAL);
    }


    ### build dummy form ###
    $newtask= new Task(array(
        'id'        =>0,
        'name'      =>__("New Milestone"),
        'project'   =>$prj_id,
        'category' =>TCATEGORY_MILESTONE,
        'status'    =>STATUS_OPEN,
        )
    );
    $PH->show('taskEdit',array('tsk'=>$newtask->id),$newtask);

}


/**
* Create a task as Version
*
* @ingroup pages
*/
function TaskNewVersion()
{
    global $PH;

    $prj_id=getOnePassedId('prj','',true,__('No project selected?')); # aborts with error if not found
    if(!$project= Project::getVisibleById($prj_id)) {
        $PH->abortWarning("invalid project-id",ERROR_FATAL);
    }

    ### build dummy form ###
    $newtask= new Task(array(
        'id'            => 0,
        'name'          => __("New Version"),
        'project'       => $prj_id,
        'status'        => STATUS_APPROVED,
        'completion'    => 100,
        'category' =>TCATEGORY_VERSION,
        'is_released'   => RELEASED_PUBLIC,
        'time_released' => getGMTString(),
        )
    );
    $PH->show('taskEdit',array('tsk'=>$newtask->id),$newtask);
}





/**
* create new folder
*
* @ingroup pages
*/
function TaskNewFolder()
{
    global $PH;

    $prj_id=getOnePassedId('prj','',true,__('No project selected?')); # aborts with error if not found
    if(!$project= Project::getVisibleById($prj_id)) {
        $PH->abortWarning("invalid project-id",ERROR_FATAL);
    }

    ### for milestone ###
    if( $milestone= Task::getVisibleById(get('for_milestone'))) {
        $for_milestone= $milestone->id;
    }
    else {
        $for_milestone= 0;
    }


    ### get id of parent_task
    $parent_task_id=0;
    {
        $task_ids= GetPassedIds('parent_task','folders_*'); # aborts with error if not found
        if(count($task_ids) >= 1) {
            $parent_task_id= $task_ids[0];
        }
    }

    ### build dummy form ###
    $newtask= new Task(array(
        'id'        =>0,
        'name'      =>__("New folder"),
        'project'   =>$prj_id,
        'is_folder' =>1,                                    #@@@ depreciated!
        'category' =>TCATEGORY_FOLDER,
        'parent_task'=>$parent_task_id,
        'for_milestone'=>$for_milestone,
        )
    );
    $PH->show('taskEdit',array('tsk'=>$newtask->id),$newtask);
}


/**
* start taskEdit-form with a new task
* - figure out prio,label and estimated time from name
*
* @ingroup pages
*/
function TaskNew()
{
    global $PH;

    $parent_task = NULL;
    $parent_task_id =0;

    ### try to figure out parent_task ###
    if($task_ids=getPassedIds('parent_task','tasks_*|folders_*',false)) {

        if(count($task_ids) != 1) {
            $PH->abortWarning(__("Please select only one item as parent"),ERROR_NOTE);
        }
        if($task_ids[0] != 0) {
            if(!$parent_task = Task::getVisibleById($task_ids[0])) {
                $PH->abortWarning(__("Insufficient rights for parent item."),ERROR_NOTE);
            }
            $parent_task_id= $parent_task->id;
        }
        else {
            $parent_task_id= 0;
        }
    }
    else {
        $parent_task_id= 0;
    }

    ### figure out project ###
    $prj_id= getOnePassedId('prj','projects_*',false);          # NOT aborts with error if not found
    if(!$prj_id) {
        if(!$parent_task) {
            $PH->abortWarning(__("could not find project"),ERROR_NOTE);
        }
        $prj_id= $parent_task->project;

    }
    if(!$project= Project::getVisibleById($prj_id)) {
        $PH->abortWarning(__("could not find project"),ERROR_NOTE);
    }

    ### make sure parent_task is valid ###
    if($parent_task_id && !$parent_task = Task::getVisibleById($parent_task_id)) {
        $PH->abortWarning(__("Parent task not found."), ERROR_NOTE);
    }


    $name= html_entity_decode(get('new_name'));  # @@@ hack to get rid of slashed strings
    $estimated='00:00:00';

    ### for milestone ###
    $for_milestone_id= 0;
    if( $milestone= Task::getVisibleById(get('for_milestone'))) {
        $for_milestone_id= $milestone->id;
    }

    ### if parent-task is milestone for some reason, avoid parenting ###
    if($parent_task && ($parent_task->category == TCATEGORY_MILESTONE || $parent_task->category == TCATEGORY_VERSION)) {
        $parent_task_id=0;
        if(!$for_milestone_id) {
            $for_milestone_id= $parent_task->id;

        }
    }

    ### category ###
    $category= TCATEGORY_TASK;
    if(!is_null($cat= get('task_category'))) {
        global $g_tcategory_names;
        if(!isset($g_tcategory_names[$cat])) {
            $category= TCATEGORY_TASK;
        }
        else {
            $category= $cat;
        }
    }

    $folder_as_docu= get('task_show_folder_as_documentation',
        ($category == TCATEGORY_DOCU) ? 1 : 0);

    ### build dummy form ###
    $newtask= new Task(array(
        'id'        =>0,
        'name'      =>$name,
        'project'   =>$prj_id,
        'state'     =>1,
        'estimated' =>$estimated,
        'category'  =>$category,
        'parent_task'=>$parent_task_id,
        'for_milestone'=>$for_milestone_id,
        'show_folder_as_documentation' =>intval($folder_as_docu)
    ));

    ### set a valid create-level ###
    $newtask->pub_level= $project->getCurrentLevelCreate();

    ### insert without editing ###
    if((get('noedit'))) {

        $newtask->insert();
        if(!$PH->showFromPage()) {
            $PH->show('projView',array('prj'=>$prj));
        }
    }

    ### pass newobject to edit-page ###
    else {
        $PH->show('taskEdit',array('tsk'=>$newtask->id),$newtask);
    }
}





/**
* Submit changes to a task
*
* @ingroup pages
*/
function taskEditSubmit()
{
    global $PH;
    global $auth;
    require_once(confGet('DIR_STREBER') . 'db/class_taskperson.inc.php');

    /**
    * keep a list of items linking to this task, task is new
    * we have to change the linking id after(!) inserting the task
    */
    $link_items=array();


    ### temporary object or from database? ###
    $tsk_id=getOnePassedId('tsk','',true,'invalid id');
    if($tsk_id == 0) {
        $task= new Task(array(
            'id'=>0,
            'project'=>get('task_project'),
        ));
        $was_category= 0;                       # undefined category for new tasks
        $was_resolved_version= 0;
    }
    else {
        if(!$task= Task::getVisiblebyId($tsk_id)) {
            $PH->abortWarning("invalid task-id");
        }
        $was_category=$task->category;
        $was_resolved_version= $task->resolved_version;
        $task->validateEditRequestTime();
    }


    ### cancel? ###
    if(get('form_do_cancel')) {
        if(!$PH->showFromPage()) {
            $PH->show('taskView',array('tsk'=>$task->id));
        }
        exit();
    }

    ### Validate integrety ###
    if(!validateFormCrc()) {
        $PH->abortWarning(__('Invalid checksum for hidden form elements'));
    }
    
    validateFormCaptcha(true);



    $was_a_folder= ($task->category == TCATEGORY_FOLDER)
                 ? true
                 : false;
    $was_released_as= $task->is_released;


    ### get project ###
    if(!$project= Project::getVisiblebyId($task->project)) {
        $PH->abortWarning("task without project?");
    }

    /**
    * adding comment (from quick edit) does only require view right...
    */
    $added_comment= false;
    {
        ### check for request feedback
        if($request_feedback= get('request_feedback')) {
            $team_members_by_nickname = array();

            foreach($project->getProjectPeople() as $pp) {
                $team_members_by_nickname[ $pp->getPerson()->nickname ] = $pp->getPerson();
            }
            $requested_people= array();

            foreach( explode('\s*,\s*', $request_feedback) as $nickname) {
                
                ### now check if this nickname is a team member
                if ($nickname = trim($nickname)) {
                    if ( isset( $team_members_by_nickname[$nickname] )) {
                        $person = $team_members_by_nickname[$nickname];

                        ### update to itemperson table...
                        if($view = ItemPerson::getAll(array('person'=>$person->id, 'item'=>$task->id))){
                            $view[0]->feedback_requested_by = $auth->cur_user->id;
                            $view[0]->update();
                        }
                        else{
                            $new_view = new ItemPerson(array(
                            'item'          =>$task->id,
                            'person'        =>$person->id,
                            'feedback_requested_by'=> $auth->cur_user->id ));
                            $new_view->insert();
                        }
                        $requested_people[]= "<b>". asHtml($nickname) ."</b>";
                    }
                    else {
                        new FeedbackWarning(sprintf(__("Nickname not known in this project: %s"), "<b>". asHtml($nickname) ."</b>"));
                    }
                } 
            }
            if( $requested_people ) {
                new FeedbackMessage(sprintf(__('Requested feedback from: %s.'), join($requested_people, ", ")));
            }
        }

        ### only insert the comment, when comment name or description are valid
        if(get('comment_name') || get('comment_description')) {

            require_once(confGet('DIR_STREBER') . 'pages/comment.inc.php');
            $valid_comment= true;

            ### new object? ###
            $comment= new Comment(array(
                'name'=> get('comment_name'),
                'description' =>get('comment_description'),
                'project' => $task->project,
                'task' => $task->id
            ));
            validateNotSpam($comment->name . $comment->description);

            ### write to db ###
            if($valid_comment) {
                if(!$comment->insert()) {
                    new FeedbackWarning(__("Failed to add comment"));
                }
                else {
                    ### change task update modification date ###
                    if(isset($task)) {
                        ### Check if now longer new ###
                        if($task->status == STATUS_NEW) {
                            global $auth;
                            if($task->created < $auth->cur_user->last_login) {
                                $task->status = STATUS_OPEN;
                            }
                        }
                        $task->update(array('modified','status'));
                    }

                    $added_comment= true;
                }
            }
        }
    }



    if($task->id != 0 && ! Task::getEditableById($task->id)) {

        if($added_comment) {
            ### display taskView ####
            if(!$PH->showFromPage()) {
                $PH->show('home',array());
            }
            exit();
        }
        else {
            $PH->abortWarning(__("Not enough rights to edit task"));
        }
    }


    $task->validateEditRequestTime();
    $status_old = $task->status;

    # retrieve all possible values from post-data (with field->view_in_forms == true)
    # NOTE:
    # - this could be an security-issue.
    # @@@ TODO: as some kind of form-edit-behaviour to field-definition
    foreach($task->fields as $f) {
        $name=$f->name;
        $f->parseForm($task);
    }

    $task->fields['parent_task']->parseForm($task);

    ### category ###
    $was_of_category = $task->category;
    if(!is_null($c= get('task_category'))) {
        global $g_tcategory_names;
        if(isset($g_tcategory_names[$c])) {
            $task->category= $c;
        }
        else {
            trigger_error("ignoring unknown task category '$c'", E_USER_NOTICE);
        }
    }
    /**
    * @@@pixtur 2006-11-17: actually this has been depreciated. is_folder updated
    * for backward compatibility only.
    */
    $task->is_folder = ($task->category == TCATEGORY_FOLDER)
                     ? 1
                     : 0;

    ### Check if now longer new ###
    if($status_old == $task->status && $task->status == STATUS_NEW) {
        global $auth;
        if($task->created < $auth->cur_user->last_login) {
            $task->status = STATUS_OPEN;
        }
    }


    /**
    * assigned to...
    * - assigments are stored in form-fiels named 'task_assign_to_??' and 'task_assigned_to_??"...
    *   ... where ?? being the id of the last assigned person(s)
    *
    * - builds up multiple arrays of person-objects (reusing the same objects due to caching)
    *   - get already assigned people into  dict. of person_id => Person
    *   - get assignments into dict. of person_id => Person
    *   - get current project-team as dict of person_id => Person
    * - checks for double-assigments
    *
    */
    {

        $assigned_people = array();
        $task_assignments = array();

        if($task->id) {
            foreach($task->getAssignedPeople() as $p) {
                $assigned_people[$p->id] = $p;
            }

            foreach($task->getAssignments() as $ta) {
                $task_assignments[$ta->person]= $ta;
            }
        }

        $team= array();
        foreach($project->getPeople() as $p) {
            $team[$p->id]= $p;
        }

        $new_task_assignments= array();                     # store assigments after(!) validation
        $forwarded = 0;
        $forward_comment = '';
        $old_task_assignments = array();
        
        if(isset($task_assignments)) {
            foreach($task_assignments as $id=>$t_old) {
                $id_new= get('task_assigned_to_'.$id);
                $forward_state = get('task_forward_to_'.$id);
                if($forward_state){
                    $forwarded = 1;
                }
                else{
                    $forwarded = 0;
                }
                $forward_comment = get('task_forward_comment_to_'.$id);
                
                if($id_new === NULL) {
                    log_message("failure. Can't change no longer existing assigment (person-id=$id item-id=$t_old->id)", LOG_MESSAGE_DEBUG);
                    #$PH->abortWarning("failure. Can't change no longer existing assigment",ERROR_NOTE);
                    continue;
                }
                
                if($id == $id_new) {
                    if($tp = TaskPerson::getTaskPeople(array('person'=>$id, 'task'=>$task->id))){
                        $tp[0]->forward = $forwarded;
                        $tp[0]->forward_comment = $forward_comment;
                        $old_task_assignments[] = $tp[0];
                    }
                    #echo " [$id] {$team[$id]->name} still assigned<br>";
                    continue;
                }

                if($id_new == 0) {
                    if(!$t_old) {
                        continue;
                    }
                    #echo " [$id] {$team[$id]->name} unassigned<br>";
                    $t_old->delete();
                    continue;
                }

                #$t_new= $task_assignments[$id_new];
                $p_new = @$team[$id_new];
                if(!isset($p_new)) {
                    $PH->abortWarning("failure during form-value passing",ERROR_BUG);
                }
                #echo " [$id] assignment changed from {$team[$id]->name} to {$team[$id_new]->name}<br>";
    
                $t_old->comment = sprintf(__("unassigned to %s","task-assignment comment"),$team[$id_new]->name);
                $t_old->update();
                $t_old->delete();
                $new_assignment= new TaskPerson(array(
                    'person'=> $team[$id_new]->id,
                    'task'  => $task->id,
                    'comment'=>sprintf(__("formerly assigned to %s","task-assigment comment"), $team[$id]->name),
                    'project'=>$project->id,
                    'forward'=>$forwarded,
                    'forward_comment'=>$forward_comment,
                ));

                $new_task_assignments[]=$new_assignment;
                $link_items[]=$new_assignment;
            }
        }

        ### check new assigments ###
        $count=0;
        while($id_new= get('task_assign_to_'.$count)) {
            
            $forward_state = get('task_forward_to_'.$count);
            if($forward_state){
                $forwarded = 1;
            }
            else{
                $forwarded = 0;
            }
            $forward_comment = get('task_forward_comment_to_'.$count);
            
            $count++;
            
            ### check if already assigned ###
            if(isset($task_assignments[$id_new])) {
                if($tp = TaskPerson::getTaskPeople(array('person'=>$id_new,'task'=>$task->id))){
                    $tp[0]->forward = $forwarded;
                    $tp[0]->forward_comment = $forward_comment;
                    $old_task_assignments[] = $tp[0];
                }

                #new FeedbackMessage(sprintf(__("task was already assigned to %s"),$team[$id_new]->name));
            }
            else {
                if(!isset($team[$id_new])) {
                    $PH->abortWarning("unknown person id $id_new",ERROR_DATASTRUCTURE);
                }

                $new_assignment= new TaskPerson(array(
                    'person'=> $team[$id_new]->id,
                    'task'  => $task->id,
                    'comment'=>"",
                    'project'=>$project->id,
                    'forward'=>$forwarded,
                    'forward_comment'=>$forward_comment,
                ));

                /**
                * BUG?
                * - inserting the new assigment before sucessfully validating the
                *   task will lead to double-entries in the database.
                */
                $new_task_assignments[] = $new_assignment;

                #$new_assignment->insert();
                $link_items[]=$new_assignment;
            }
        }
    }
    
    if($task->isOfCategory(array(TCATEGORY_VERSION, TCATEGORY_MILESTONE))) {
        if($is_released=get('task_is_released')) {
            if(!is_null($is_released)) {
                $task->is_released = $is_released;
            }
        }
    }

    ### pub level ###
    if($pub_level=get('task_pub_level')) {
        if($task->id) {
             if($pub_level > $task->getValidUserSetPublicLevels() ) {
                 $PH->abortWarning('invalid data',ERROR_RIGHTS);
             }
        }
        #else {
        #    #@@@ check for person create rights
        #}
        $task->pub_level = $pub_level;
    }

    ### check project ###
    if($task->id == 0) {
        if(!$task->project=get('task_project')) {
            $PH->abortWarning("task requires project to be set");
        }
    }

    ### get parent_task ###
    $is_ok= true;
    $parent_task= NULL;
    if($task->parent_task) {
        $parent_task= Task::getVisibleById($task->parent_task);
    }

    ### validate ###
    if(!$task->name) {
        new FeedbackWarning(__("Task requires name"));
        $task->fields['name']->required=true;
        $task->fields['name']->invalid=true;
        $is_ok= false;
    }
    ### task-name already exist ###
    else if($task->id == 0){
        $other_tasks = array();

        if($parent_task) {
            $other_tasks= Task::getAll(array(
                'project' => $project->id,
                'parent_task'=> $parent_task->id,
                'status_min'=> STATUS_NEW,
                'status_max'=> STATUS_CLOSED,
                'visible_only' => false,
            ));
        }
        else {
            $other_tasks= Task::getAll(array(
                'project' => $project->id,
                'parent_task'=> 0,
                'status_min'=> STATUS_NEW,
                'status_max'=> STATUS_CLOSED,
                'visible_only' => false,
            ));
        }
        foreach($other_tasks as $ot) {
            if(!strcasecmp($task->name, $ot->name)) {
                $is_ok = false;
                new FeedbackWarning(sprintf(__('Task called %s already exists'), $ot->getLink(false)));
                break;
            }
        }
    }

    ### automatically close resolved tasks ###
    if($task->resolve_reason && $task->status < STATUS_COMPLETED) {
        $task->status = STATUS_COMPLETED;
        new FeedbackMessage(sprintf(__('Because task is resolved, its status has been changed to completed.')));
    }


    ### Check if resolved tasks should be completed ###
    if($task->resolved_version != 0 && $task->status < STATUS_COMPLETED) {
        new FeedbackWarning(sprintf(__('Task has resolved version but is not completed?')));
        $task->fields['resolved_version']->invalid= true;
        $task->fields['status']->invalid= true;
        $is_ok = false;
    }

    ### Check if completion should be 100% ###
    if ($task->status >= STATUS_COMPLETED) {
        $task->completion = 100;
    }


    ### repeat form if invalid data ###
    if(!$is_ok) {
        $PH->show('taskEdit',NULL,$task);
        exit();
    }

    #--- write to database -----------------------------------------------------------------------

    #--- be sure parent-task is folder ---
    if($parent_task) {

        if($parent_task->isMilestoneOrVersion()) {
            if($parent_task->is_folder) {
                $parent_task->is_folder= 0;
                $parent_task->update(array('is_folder'),false);
            }
            $PH->abortWarning(__("Milestones may not have sub tasks"));
        }
        else if($parent_task->category != TCATEGORY_FOLDER) {
            $parent_task->category= TCATEGORY_FOLDER;
            $parent_task->is_folder= 1;
            if($parent_task->update()) {
                new FeedbackMessage(__("Turned parent task into a folder. Note, that folders are only listed in tree"));
            }
            else {
                trigger_error(__("Failed, adding to parent-task"),E_USER_WARNING);
                $PH->abortWarning(__("Failed, adding to parent-task"));

            }
        }
    }

    ### ungroup child tasks? ###
    if($was_a_folder && $task->category != TCATEGORY_FOLDER) {

        $num_subtasks= $task->ungroupSubtasks();            # @@@ does this work???


        /**
        * note: ALSO invisible tasks should be updated, so do not check for visibility here.
        */
        $parent= Task::getById($task->parent_task);
        $parent_str= $parent
            ? $parent->name
            : __('Project');

        if($num_subtasks) {
            new FeedbackMessage(sprintf(__("NOTICE: Ungrouped %s subtasks to <b>%s</b>"),$num_subtasks, $parent_str));
        }
    }

    if($task->id && !get('task_issue_report')) {
        $task_issue_report = $task->issue_report;
    }
    else if($task->issue_report != get('task_issue_report')) {
        trigger_error("Requesting invalid issue report id for task!", E_USER_WARNING);
        $task_issue_report= get('task_issue_report');
    }
    else {
        $task_issue_report = 0;
    }
    
        
    ### consider issue-report? ###
    #$task_issue_report= get('task_issue_report');
    if( $task->category == TCATEGORY_BUG || (isset($task_issue_report) && $task_issue_report) ) {


        ### new report as / temporary ###
        if($task_issue_report == 0 || $task_issue_report == -1) {

            $issue= new Issue(array(
                'id'=>0,
                'project'   => $project->id,
                'task'      => $task->id,
            ));

            ### querry form-information ###
            foreach($issue->fields as $f) {
                $name=$f->name;
                $f->parseForm($issue);
            }

            global $g_reproducibility_names;
            if(!is_null($rep= get('issue_reproducibility'))) {
                if(isset($g_reproducibility_names[$rep])) {
                    $issue->reproducibility= intval($rep);
                }
                else {
                    $issue->reproducibility= REPRODUCIBILITY_UNDEFINED;
                }
            }

            global $g_severity_names;
            if(!is_null($sev= get('issue_severity'))) {
                if(isset($g_severity_names[$sev])) {
                    $issue->severity= intval($sev);
                }
                else {
                    $issue->severity= SEVERITY_UNDEFINED;
                }
            }

            ### write to db ###
            if(!$issue->insert()) {
                trigger_error("Failed to insert issue to db",E_USER_WARNING);
            }
            else {
                $link_items[]= $issue;
                $task->issue_report= $issue->id;
            }
        }
        ### get from database ###
        else if($issue= Issue::getById($task_issue_report)) {

            ### querry form-information ###
            foreach($issue->fields as $f) {
                $name=$f->name;
                $f->parseForm($issue);
            }

            global $g_reproducibility_names;
            if(!is_null($rep= get('issue_reproducibility'))) {
                if(isset($g_reproducibility_names[$rep])) {
                    $issue->reproducibility= intval($rep);
                }
                else {
                    $issue->reproducibility= REPRODUCIBILITY_UNDEFINED;
                }
            }

            global $g_severity_names;
            if(!is_null($sev= get('issue_severity'))) {
                if(isset($g_severity_names[$sev])) {
                    $issue->severity= intval($sev);
                }
                else {
                    $issue->severity= SEVERITY_UNDEFINED;
                }
            }


            ### write to db ###
            if(!$issue->update()) {
                trigger_error("Failed to write issue to DB (id=$issue->id)", E_USER_WARNING);
            }

            if($task->issue_report != $issue->id) {         # additional check, actually not necessary
                trigger_error("issue-report as invalid id ($issue->id). Should be ($task->issue_report) Please report this bug.",E_USER_WARNING);
            }
        }
        else {
            trigger_error("Could not get issue with id $task->issue_report from database",E_USER_WARNING);
        }
    }

    ### write to db ###
    if($task->id == 0) {
        $task->insert();

        ### write task-assigments ###
        foreach($new_task_assignments as $nta) {
            $nta->insert();
        }

        ### now we now the id of the new task, link the other items
        foreach($link_items as $i) {
            $i->task= $task->id;
            $i->update();
        }        
        new FeedbackMessage(sprintf(__("Created %s %s with ID %s","Created <type> <name> with ID <id>..."),  
                $task->getLabel(),
                $task->getLink(false),
                $task-> id)
            );
    }
    else {

        ### write task-assigments ###
        foreach($new_task_assignments as $nta) {
            $nta->insert();
        }
        
        foreach($old_task_assignments as $ota){
            $ota->update();
        }

        new FeedbackMessage(sprintf(__("Changed %s %s with ID %s","type,link,id"),  $task->getLabel(), $task->getLink(false),$task->id));
        $task->update();
        $project->update(array(), true);
    }


    ### add any recently resolved tasks if this is a just released version  ###
    if($task->category == TCATEGORY_VERSION && $was_category != TCATEGORY_VERSION) {
        if($resolved_tasks= Task::getAll(array(
            'project'           => $task->project,
            'status_min'        => 0,
            'status_max'        => 10,
            'resolved_version'  => RESOLVED_IN_NEXT_VERSION,
        ))) {
            foreach($resolved_tasks as $rt) {
                $rt->resolved_version= $task->id;
                $rt->update(array('resolved_version'));
            }
            new FeedbackMessage(sprintf(__('Marked %s tasks to be resolved in this version.'), count($resolved_tasks)));
        }
    }

    ### notify on change ###
    $task->nowChangedByUser();

    ### create another task ###
    if(get('create_another')) {


        ### build dummy form ###
        $newtask= new Task(array(
            'id'        =>0,
            'name'      =>__('Name'),
            'project'   =>$task->project,
            'state'     =>1,
            'prio'      =>$task->prio,
            'label'     =>$task->label,
            'parent_task'=>$task->parent_task,
            'for_milestone'=>$task->for_milestone,
            'category'  =>$task->category,
        ));


        $PH->show('taskEdit',array('tsk'=>$newtask->id),$newtask);
    }
    else {

        ### go to task, if new
        if($tsk_id == 0) {
            $PH->show('taskView',array('tsk' => $task->id));
            exit();
        }
        ### display taskView ####
        else if(!$PH->showFromPage()) {
            $PH->show('home',array());
        }
    }
}


/**
* to tasks to folder...
*
* @ingroup pages
*
* NOTE: this works either...
* - directly by passing a target folder in 'folder' or 'folders_*'
* - in two steps, whereas
*   - the passed task-ids are keept as hidden fields,
*   - a list with folders in been rendered
*   - a flag 'from_selection' is set
*   - after submit, the kept tasks are moved to 'folders_*'
*
*/
function TasksMoveToFolder()
{
    global $PH;

    $task_ids= getPassedIds('tsk','tasks_*');

    if(!$task_ids) {
        $PH->abortWarning(__("Select some tasks to move"));
        exit();
    }



    /**
    * by default render list of folders...
    */
    $target_id=-1;

    /**
    * ...but, if folder was given, directly move tasks...
    */
    $folder_ids= getPassedIds('folder','folders_*');
    if(count($folder_ids) == 1) {
        if($folder_task= Task::getVisibleById($folder_ids[0])) {
            $target_id= $folder_task->id;
        }
    }

    /**
    * if no folders was selected, move tasks to project root
    */
    else if(get('from_selection')) {
        $target_id= 0;
    }


    if($target_id != -1) {


        if($target_id != 0){
            if(!$target_task= Task::getEditableById($target_id)) {
                $PH->abortWarning(__("insufficient rights"));

            }
            ### get path of target to check for cycles ###
            $parent_tasks= $target_task->getFolder();
            $parent_tasks[]= $target_task;
        }
        else {
            $parent_tasks=array();
        }


        $count=0;
        foreach($task_ids as $id) {
            if($task= Task::getEditableById($id)) {

                ### check if tasks target_id is own child ###
                $cycle_flag= false;
                foreach($parent_tasks as $pt) {
                    if($pt->id == $task->id) {
                        $cycle_flag= true;
                        break;
                    }
                }
                if($cycle_flag) {
                    new FeedbackWarning(sprintf(__("Can not move task <b>%s</b> to own child."), $task->name));
                }
                else {
                    $task->parent_task= $target_id;
                    $task->update();
                    $task->nowChangedByUser();
                }
            }
            else {
                new FeedbackWarning(sprintf(__("Can not edit tasks %s"), $task->name));
            }
        }

        ### return to from-page? ###
        if(!$PH->showFromPage()) {
            $PH->show('home');
        }
        exit();
    }

    /**
    * build page folder lists...
    */

    ### get project ####
    if(!$task= Task::getVisibleById($task_ids[0])) {
        $PH->abortWarning("could not get task", ERROR_BUG);
    }

    if(!$project= Project::getVisibleById($task->project)) {
        $PH->abortWarning("task without project?", ERROR_BUG);
    }


    ### set up page and write header ####
    {
        $page= new Page(array('use_jscalendar'=>false));
        $page->cur_tab='projects';
        $page->type= __("Edit tasks");
        $page->title="$project->name";
        $page->title_minor=__("Select folder to move tasks into");

        $page->crumbs= build_project_crumbs($project);

        $page->options[]= new NaviOption(array(
            'target_id'     =>'tasksMoveToFolder',
        ));

        echo(new PageHeader);
    }
    echo (new PageContentOpen);


    ### write form #####
    {
        ### write tasks as hidden entry ###
        foreach($task_ids as $id) {
            if($task= Task::getEditableById($id)) {

                echo "<input type=hidden name='tasks_{$id}_chk' value='1'>";
            }
        }

        ### write list of folders ###
        {
            $list= new ListBlock_tasks();
            $list->query_options['show_folders']= true;
            $list->query_options['folders_only']= true;
            $list->query_options['project']= $project->id;
            $list->groupings= NULL;
            $list->block_functions= NULL;
            $list->id= 'folders';
            unset($list->columns['status']);
            unset($list->columns['date_start']);
            unset($list->columns['days_left']);
            unset($list->columns['created_by']);
            unset($list->columns['label']);
            unset($list->columns['project']);

            $list->functions= array();

            $list->active_block_function = 'tree';


            $list->print_automatic($project,NULL);
        }

        echo __("(or select nothing to move to project root)"). "<br> ";

        echo "<input type=hidden name='from_selection' value='1'>";             # keep flag to ungroup tasks
        echo "<input type=hidden name='project' value='$project->id'>";
        $button_name=__("Move items");
        echo "<input class=button2 type=submit value='$button_name'>";

        $PH->go_submit='tasksMoveToFolder';

    }
    echo (new PageContentClose);
    echo (new PageHtmlEnd());

}


/**
* tasksDelete
*
* @ingroup pages
*
* \NOTE sub-tasks of tasks are not deleted but ungrouped
*/
function TasksDelete()
{
    global $PH;
    $tsk=get('tsk');
    $tasks_selected=get('tasks_*');
    $ids=getPassedIds('tsk','tasks_*');

    $tasks= array();

    if(count($ids)==1) {
        $tsk=$ids[0];
        if(!$task= Task::getEditableById($tsk)) {
            $PH->abortWarning(__('insufficient rights'),ERROR_RIGHTS);
            $PH->show('home');
            return;
        }
        $tasks[]= $task;
    }
    else if($ids) {
       #--- get tasks ----

        $num_tasks=count($tasks);
        foreach($ids as $id) {
            if(!$task= Task::getEditableById($id)) {
                $PH->abortWarning("invalid task-id");
            }
            $tasks[]= $task;
        }
    }

    if($tasks) {

        $num_subtasks = 0;
        $num_tasks= 0;

        foreach($tasks as $task) {

            $num_subtasks+= $task->ungroupSubtasks();

            if(!$task->delete()) {
                new FeedbackWarning(sprintf(__("Failed to delete task %s"), $task->name));
            }
            else {
                $num_tasks++;
            }
        }
        new FeedbackMessage(sprintf(__("Moved %s tasks to trash"),$num_tasks));

        if($num_subtasks) {
            new FeedbackMessage(sprintf(__(" ungrouped %s subtasks to above parents."),$num_subtasks));
        }

        ### return to from-page? ###
        if(!$PH->showFromPage()) {
            $PH->show('home');
        }
    }
    else {
        new FeedbackHint(__("No task(s) selected for deletion..."));
        if(!$PH->showFromPage()) {
            $PH->show('home');
        }
    }
}

/**
* Restore deleted task
*
* @ingroup pages
*/
function TasksUndelete()
{
    global $PH;
    $tsk=get('tsk');
    $tasks_selected=get('tasks_*');
    $ids=getPassedIds('tsk','tasks_*');


    if(count($ids)==1) {
        $tsk=$ids[0];
        $task= Task::getEditableById($tsk);
        if(!$task) {
            new FeedbackWarning(__("Could not find task"));
            $PH->show('home');
            return;
        }

        ### check user-rights ###
        if(!$project= Project::getVisibleById($task->project)) {
            $PH->abortWarning("task without project?", ERROR_BUG);
        }

        ### delete task ###
        if($task->state!= -1) {
            new FeedbackHint(sprintf(__("Task <b>%s</b> does not need to be restored"),$task->name));
        }
        else {
            $task->state=1;
            if($task->update()) {
                new FeedbackMessage(sprintf(__("Task <b>%s</b> restored"),$task->name));
                $task->nowChangedByUser();
            }
            else {
                new FeedbackMessage(sprintf(__("Failed to restore Task <b>%s</b>"),$task->name));
            }
        }

        ### go to project view ###
        ### return to from-page? ###
        if(!$PH->showFromPage()) {
            $PH->show('projView',array('prj'=>$project->id));
        }
    }
    else if($ids) {
       #--- get tasks ----
        $tasks=array();
        $num_tasks=count($tasks);
        $num_subtasks=0;

        foreach($ids as $id) {

            if(!$task = Task::getEditableById($id)) {
                new FeedbackWarning("Could not find task");
                $PH->show('home');
                return;
            }

            ### delete task ###
            if($task->state!= -1) {
                new FeedbackHint(sprintf(__("Task <b>%s</b> do not need to be restored"), $task->name));
            }
            else {
                $task->state=1;
                if($task->update()) {
                    new FeedbackMessage(sprintf(__("Task <b>%s</b> restored"),$task->name));
                }
                else {
                    new FeedbackWarning(sprintf(__("Failed to restore Task <b>%s</b>"),$task->name));
                }
            }
        }

        ### return to from-page? ###
        if(!$PH->showFromPage()) {
            $PH->show('home');
        }
    }
    else {
        new FeedbackHint(__("No task(s) selected for restoring..."));
        if(!$PH->showFromPage()) {
            $PH->show('home');
        }
    }
}


/**
* Mark tasks as being completed
*
* @ingroup pages
*/
function TasksComplete()
{
    global $PH;

    $ids= getPassedIds('tsk','tasks_*');

    if(!$ids) {
        $PH->abortWarning(__("Select some task(s) to mark as completed"), ERROR_NOTE);
        return;
    }

    $count=0;
    $count_subtasks=0;
    $num_errors=0;

    foreach($ids as $id) {
        if($task= Task::getEditableById($id)) {

            $count++;
            $task->status=5;
            $task->date_closed= gmdate("Y-m-d", time());
            $task->completion=100;
            $task->update();
            $task->nowChangedByUser();

            ### get all subtasks ###
            if($subtasks= $task->getSubtasks()) {
                foreach($subtasks as $st) {
                    if($subtask_editable= Task::getEditableById($st->id)) {
                        $count_subtasks++;
                        $subtask_editable->status=  STATUS_COMPLETED;
                        $subtask_editable->date_closed= gmdate("Y-m-d", time());
                        $subtask_editable->completion=100;
                        $subtask_editable->update();
                        $subtask_editable->nowChangedByUser();
                        $subtask_editable->resolved_version= RESOLVED_IN_NEXT_VERSION;
                    }
                    else {
                        $num_errors++;
                    }
                }
            }
        }
        else {
            $num_errors++;
        }
    }
    $str_subtasks= $count_subtasks
     ? "(including $count_subtasks subtasks)"
     : "";

    new FeedbackMessage(sprintf(__("Marked %s tasks (%s subtasks) as completed."),$count,$str_subtasks)) ;
    if($num_errors) {
        new FeedbackWarning(sprintf(__("%s error(s) occured"), $num_errors));
    }

    ### return to from-page ###
    if(!$PH->showFromPage()) {
        $PH->show('home');
    }
}

/**
* Create a task as being approved
*
* @ingroup pages
*/
function TasksApproved()
{
    global $PH;

    $ids= getPassedIds('tsk','tasks_*');

    if(!$ids) {
        $PH->abortWarning(__("Select some task(s) to mark as approved"), ERROR_NOTE);
        return;
    }

    $count=0;
    $num_errors=0;
    foreach($ids as $id) {
        if($task= Task::getEditableById($id)) {

            $count++;
            $task->status = STATUS_APPROVED;
            $task->date_closed = gmdate("Y-m-d", time());
            $task->completion = 100;
            $task->update();
            $task->nowChangedByUser();
        }
        else {
            $num_errors++;
        }

    }
    new FeedbackMessage(sprintf(__("Marked %s tasks as approved and hidden from project-view."),$count));
    if($num_errors) {
        new FeedbackWarning(sprintf(__("%s error(s) occured"), $num_errors));
    }

    ### return to from-page ###
    if(!$PH->showFromPage()) {
        $PH->show('home');
    }
}


/**
* Create a task as closed
*
* @ingroup pages
*/
function TasksClosed()
{
    global $PH;

    $ids= getPassedIds('tsk','tasks_*');

    if(!$ids) {
        $PH->abortWarning(__("Select some task(s) to mark as closed"), ERROR_NOTE);
        return;
    }

    $count=0;
    $num_errors=0;
    foreach($ids as $id) {
        if($task= Task::getEditableById($id)) {

            $count++;
            $task->status = STATUS_CLOSED;
            $task->date_closed = gmdate("Y-m-d", time());
            $task->completion = 100;
            $task->update();
            $task->nowChangedByUser();
        }
        else {
            $num_errors++;
        }

    }
    new FeedbackMessage(sprintf(__("Marked %s tasks as closed."),$count));
    if($num_errors) {
        new FeedbackWarning(sprintf(__("Not enough rights to close %s tasks."), $num_errors));
    }

    ### return to from-page ###
    if(!$PH->showFromPage()) {
        $PH->show('home');
    }
}

/**
* Reopen tasks
*
* @ingroup pages
*/
function TasksReopen()
{
    global $PH;

    $ids= getPassedIds('tsk','tasks_*');

    if(!$ids) {
        $PH->abortWarning(__("Select some task(s) to reopen"), ERROR_NOTE);
        return;
    }

    $count  =0;
    $num_errors =0;
    foreach($ids as $id) {
        if($task= Task::getEditableById($id)) {

            $count++;
            $task->status=STATUS_OPEN;
            $task->update();
            $task->nowChangedByUser();
        }
        else {
            $num_errors++;
        }

    }
    new FeedbackMessage(sprintf(__("Reopened %s tasks."),$count));
    if($num_errors) {
        new FeedbackWarning(sprintf(__("%s error(s) occured"), $num_errors));
    }

    ### return to from-page ###
    if(!$PH->showFromPage()) {
        $PH->show('home');
    }
}




/**
* Toggle task folders
*
* @ingroup pages
*/
function TaskToggleViewCollapsed()
{
    global $PH;
    global $auth;

    $ids= getPassedIds('tsk','tasks_*');

    if(!$ids) {
        $PH->abortWarning(__("Select some task(s)"), ERROR_NOTE);
        return;
    }

    $count  = 0;
    $num_errors = 0;
    foreach($ids as $id) {
        if($task= Task::getVisibleById($id)) {

            if($task->view_collapsed) {
                $task->view_collapsed= 0;
            }
            else {
                $task->view_collapsed= 1;
            }
            if(!$task->update(array('view_collapsed'),false)) {
                new FeedbackError(__("Could not update task"));
                $num_errors++;
            }
            else {
                $count++;
            }
        }
        else {
            $num_errors++;
        }
    }
    if($num_errors) {
        new FeedbackWarning(sprintf(__("%s error(s) occured"), $num_errors));
    }

    ### return to from-page ###
    if(!$PH->showFromPage()) {
        $PH->show('home');
    }
}



/**
* add an issue-report to an existing task
*
* @ingroup pages
*/
function TaskAddIssueReport()
{
    global $PH;


    $id= getOnePassedId('tsk','tasks_*',true,__('No task selected to add issue-report?'));

    if($task= Task::getEditableById($id)) {
        if($task->issue_report) {
            $PH->abortWarning(__("Task already has an issue-report"));
            exit();
        }

        ### check user-rights ###
        if(!$project= Project::getVisibleById($task->project)) {
            $PH->abortWarning(__("task without project?"), ERROR_BUG);
        }

        $task->issue_report= -1;
        new FeedbackHint(__("Adding issue-report to task"));
        $PH->show('taskEdit',array('tsk'=>$task->id),$task);
        exit();

    }
    else {
        new FeedbackWarning(__("Could not find task"));
    }

    ### return to from-page ###
    if(!$PH->showFromPage()) {
        $PH->show('home');
    }
}


/**
* Edit description of a task
*
* @ingroup pages
*/
function taskEditDescription($task=NULL)
{
    global $PH;

    ### object or from database? ###
    if(!$task) {

        $id= getOnePassedId('tsk','tasks_*');

        if(!$task = Task::getEditableById($id)) {
            $PH->abortWarning(__("Select a task to edit description"), ERROR_NOTE);
            return;
        }
    }

    ### set up page and write header ####
    {
        $page= new Page(array('use_jscalendar'=>false, 'autofocus_field'=>'task_name'));

        initPageForTask($page, $task);

        $page->title_minor= __("Edit description");

        echo(new PageHeader);
    }
    echo (new PageContentOpen);


    ### write form #####
    {
        require_once(confGet('DIR_STREBER') . 'render/render_form.inc.php');

        $block=new PageBlock(array(
            'id'    =>'edit',
        ));
        $block->render_blockStart();

        $form=new PageForm();
        $form->button_cancel=true;


        $form->add(new Form_HiddenField('task_id','',$task->id));
        $form->add($task->fields['name']->getFormElement($task));
        $e= $task->fields['description']->getFormElement($task);
        $e->rows=22;
        $form->add($e);

        echo ($form);

        $block->render_blockEnd();

        $PH->go_submit= 'taskEditDescriptionSubmit';
    }
    echo (new PageContentClose);
    echo (new PageHtmlEnd);


}

/**
* Submit changes to the description of a task
*
* @ingroup pages
*/
function taskEditDescriptionSubmit()
{
    global $PH;

    ### cancel? ###
    if(get('form_do_cancel')) {
        if(!$PH->showFromPage()) {
            $PH->show('taskView',array('tsk'=>$task->id));
        }
        exit();
    }

    if(!$task = Task::getEditableById(intval(get('task_id')))) {
        $PH->abortWarning("unknown task-id");
    }

    $name= get('task_name');
    if(!is_null($name)) {
        $task->name= $name;
    }

    $description= get('task_description');
    if(!is_null($description)) {
        $task->description= $description;
    }


    ### validate ###
    if(!$task->name) {
        new FeedbackWarning(__("Task requires name"));
    }

    ### repeat form if invalid data ###
    if(!$task->name) {
        $PH->show('taskEditDescription',NULL,$task);

        exit();
    }


    ### write to db ###
    $task->update(array('name','description'));

    ### return to from-page? ###
    if(!$PH->showFromPage()) {
        $PH->show('taskView',array('tsk'=>$task->id));
    }
}




/**
* View efforts for a task
*
* @ingroup pages
*/
function TaskViewEfforts()
{
    global $PH;

    require_once(confGet('DIR_STREBER') . 'lists/list_efforts.inc.php');

    ### get current project ###
    $id=getOnePassedId('task','tasks_*');
    if(!$task=Task::getVisibleById($id)) {
        $PH->abortWarning("invalid task-id");
        return;
    }

    ### create from handle ###
    $PH->defineFromHandle(array('task'=>$task->id));

    if(!$project= Project::getVisibleById($task->project)) {
        $PH->abortWarning("not enough rights");
    }

    ### set up page ####
    {
        $page= new Page();
        initPageForTask($page, $task, $project);

        $page->title_minor= __("Task Efforts");

        ### page functions ###
        $page->add_function(new PageFunction(array(
            'target'=>'effortNew',
            'params'=>array('task'=>$task->id),
            'icon'=>'new',
            'name'=>__('new Effort'),
        )));


        ### render title ###
        echo(new PageHeader);
    }
    echo (new PageContentOpen);

    #--- list efforts --------------------------------------------------------------------------
    {
        $order_by=get('sort_'.$PH->cur_page->id."_efforts");

        require_once(confGet('DIR_STREBER') . 'db/class_effort.inc.php');
        $efforts= Effort::getAll(array(
            'task'      => $task->id,
            'order_by'  => $order_by,

        ));
        $list= new ListBlock_efforts();
        $list->render_list($efforts);
    }

    ### 'add new task'-field ###
    $PH->go_submit='effortNew';
    echo '<input type="hidden" name="task" value="'.$id.'">';

    echo (new PageContentClose);
    echo (new PageHtmlEnd());
}


/**
* Edit multiple tasks
*
* @ingroup pages
*/
function TaskEditMultiple()
{
    global $PH;

    $task_ids= getPassedIds('tsk','tasks_*');

    if(!$task_ids) {
        $PH->abortWarning(__("Select some tasks to move"));
        exit();
    }

    $count  = 0;
    $count_subtasks = 0;
    $errors = 0;

    $last_milestone_id=NULL;
    $different_milestones=false;

    $last_status=NULL;
    $different_status=false;

    $project= NULL;

    $tasks= array();

    $task_assignments = array();
    $task_assignments_count = array();
    $different_assignments = false;

    $edit_fields=array(
        'category',
        'prio',
        'status',
        'for_milestone',
        'resolved_version',
        'resolve_reason',
        'label',
        'pub_level'
    );
    $different_fields=array();  # hash containing fieldnames which are different in tasks

    $count=0;

    foreach($task_ids as $id) {
        if($task= Task::getEditableById($id)) {

            $tasks[]= $task;

            ## get assigned people ##
            if($task_people = $task->getAssignedPeople(false))
            {
                $counter = 0;
                foreach($task_people as $tp){
                    $task_assignments[$task->id][$counter++] = $tp->id;
                }
                $task_assignments_count[$task->id] = count($task_people);
            }
            ## if nobody is assigned
            else{
                $task_assignments[$task->id][0] = '__none__';
                $task_assignments_count[$task->id] = 0;
            }

            ### check project for first task
            if(count($tasks) == 1) {

                ### make sure all are of same project ###
                if(!$project = Project::getVisibleById($task->project)) {
                    $PH->abortWarning('could not get project');
                }
            }
            else {
                if($task->project != $tasks[0]->project) {
                    $PH->abortWarning(__("For editing all tasks must be of same project."));
                }

                foreach($edit_fields as $field_name) {
                    if($task->$field_name !== $tasks[0]->$field_name) {
                        $different_fields[$field_name]= true;
                    }
                }

                ## check if tasks have different people assigned ##
                if($task_assignments_count[$tasks[0]->id] != $task_assignments_count[$task->id]){
                    $different_assignments = true;
                }
                else{
                    for($i = 0; $i < $task_assignments_count[$tasks[0]->id]; $i++){
                        if($task_assignments[$tasks[0]->id][$i] != $task_assignments[$task->id][$i]){
                            $different_assignments = true;
                        }
                    }
                }
            }
        }
    }


    ### set up page and write header ####
    {
        $page= new Page(array('use_jscalendar'=>true));
        $page->cur_tab='projects';


        $page->options[]= new naviOption(array(
            'target_id'     =>'taskEdit',
        ));

        $page->type= __("Edit multiple tasks","Page title");

        $page->title= sprintf(__("Edit %s tasks","Page title"), count($tasks));

        echo(new PageHeader);
    }
    echo (new PageContentOpen);


    ### write form #####
    {
        require_once(confGet('DIR_STREBER') . 'render/render_form.inc.php');

        global $g_status_names;

        echo "<ol>";
        foreach($tasks as $t) {
            echo "<li>" . $t->getLink(false). "</li>";
        }
        echo "</ol>";

        $form=new PageForm();
        $form->button_cancel=true;


        ### category ###
        {
            $a= array();
            global $g_tcategory_names;
            foreach($g_tcategory_names as $s=>$n) {
                $a[$s]=$n;
            }
            if(isset($different_fields['category'])) {
                $a['__dont_change__']= ('-- ' . __('keep different'). ' --');
                $form->add(new Form_Dropdown('task_category',__("Category"),array_flip($a),  '__dont_change__'));
            }
            else {
                $form->add(new Form_Dropdown('task_category',__("Category"),array_flip($a),  $tasks[0]->category));
            }
        }


        ### status ###
        {
            $st=array();
            foreach($g_status_names as $s => $n) {
                if($s >= STATUS_NEW) {
                    $st[$s]=$n;
                }
            }
            if(isset($different_fields['status'])) {
                $st['__dont_change__']= ('-- ' . __('keep different'). ' --');
                #$st[('-- ' . __('keep different'). ' --')]=  '__dont_change__';
                $form->add(new Form_Dropdown('task_status',__("Status"),array_flip($st),  '__dont_change__'));
            }
            else {
                $form->add(new Form_Dropdown('task_status',__("Status"),array_flip($st),  $tasks[0]->status));
            }
        }


        ### public-level ###
        if(($pub_levels=$task->getValidUserSetPublicLevels())
            && count($pub_levels)>1) {
            if(isset($different_fields['pub_level'])) {
                $pub_levels[('-- ' . __('keep different'). ' --')]= '__dont_change__';
                $form->add(new Form_Dropdown('task_pub_level',  __("Publish to"),$pub_levels, '__dont_change__'));
            }
            else {
                $form->add(new Form_Dropdown('task_pub_level',  __("Publish to"),$pub_levels,$tasks[0]->pub_level));
            }

        }

        ### labels ###
        $labels=array(__('undefined') => 0);
        $counter= 1;
        foreach(explode(",",$project->labels) as $l) {
            $labels[$l]=$counter++;
        }
        if(isset($different_fields['label'])) {
            $labels[('-- ' . __('keep different'). ' --')]= '__dont_change__';
            $form->add(new Form_Dropdown('task_label',  __("Label"),$labels, '__dont_change__'));
        }
        else {
            $form->add(new Form_Dropdown('task_label',  __("Label"),$labels,$tasks[0]->label));
        }



        ### prio ###
        {
            global $g_prio_names;
            $pr= array();
            foreach($g_prio_names as $key => $value) {
                $pr[$key]= $value;
            }
            if(isset($different_fields['prio'])) {
                $pr['__dont_change__']= ('-- ' . __('keep different'). ' --');
                $form->add(new Form_Dropdown('task_prio',__("Prio"),array_flip($pr),  '__dont_change__'));
            }
            else {
                $form->add(new Form_Dropdown('task_prio',__("Prio"),array_flip($pr),  $tasks[0]->prio));
            }
        }


        ### milestone ###
        {
            $grouped_milestone_options= $project->buildPlannedForMilestoneList();
            if(isset($different_fields['for_milestone'])) {                
                $grouped_milestone_options[NO_OPTION_GROUP]['__dont_change__']= ('-- ' . __('keep different'). ' --');
                $form->add(new Form_DropdownGrouped(
                                'task_for_milestone',
                                 __('For Milestone'), 
                                 $grouped_milestone_options,
                                 '__dont_change__'
                               ));
            }
            else {
                $form->add(new Form_DropdownGrouped(
                                'task_for_milestone', 
                                __('For Milestone'), 
                                $grouped_milestone_options,
                                $tasks[0]->for_milestone
                                ));
            }
        }

        ### resolved_version ###
        {
            $grouped_resolve_options= $project->buildResolvedInList();
            if(isset($different_fields['resolved_version'])) {
                $grouped_resolve_options[NO_OPTION_GROUP]['__dont_change__']= ('-- ' . __('keep different'). ' --');
                $form->add(new Form_DropdownGrouped(
                                'task_resolved_version', 
                                __('resolved in Version'), 
                                $grouped_resolve_options,
                                '__dont_change__'
                                ));
            }
            else {
                $form->add(new Form_DropdownGrouped(
                            'task_resolved_version', 
                            __('resolved in Version'), 
                            $project->buildResolvedInList(), 
                            $tasks[0]->resolved_version
                            ));
            }
        }



        ### resolve reason ###
        {
            global $g_resolve_reason_names;
            $rr= array();
            foreach($g_resolve_reason_names as $key => $value) {
                $rr[$key]= $value;
            }
            if(isset($different_fields['resolve_reason'])) {
                $rr['__dont_change__']= ('-- ' . __('keep different') . ' --');
                $form->add(new Form_Dropdown('task_resolve_reason',__("Resolve Reason"),array_flip($rr),  '__dont_change__'));
            }
            else {
                $form->add(new Form_Dropdown('task_resolve_reason',__("Resolve Reason"),array_flip($rr),  $tasks[0]->resolve_reason));
            }
        }

        ## assignement ##
        {
            $ass = array();
            $ass_also = array();

            ## get project team ##
            if($proj_people = $project->getPeople()){
                foreach($proj_people as $pp){
                    $ass[$pp->id] = $pp->name;
                    $ass_also[$pp->id] = $pp->name;
                }
            }

            ## different people assigend? ##
            if($different_assignments){
                $ass['__dont_change__'] = ('-- ' . __('keep different') . ' --');
                $form->add(new Form_Dropdown('task_assignement_diff', __('Assigned to'), array_flip($ass), '__dont_change__'));

                $ass_also['__select_person__'] = ('-- ' . __('select person') . ' --');
                $form->add(new Form_Dropdown('task_assignement_also_diff', __('Also assigned to'), array_flip($ass_also), '__select_person__'));
            }
            else{
                $ass['__none__'] = ('-- ' . __('none') . ' --');
                foreach($task_assignments[$tasks[0]->id] as $key=>$value)
                {
                    $form->add(new Form_Dropdown('task_assign_to_'.$task_assignments[$tasks[0]->id][$key], __('Assigned to'), array_flip($ass), $task_assignments[$tasks[0]->id][$key]));
                }

                $ass_also['__select_person__'] = ('-- ' . __('select person') . ' --');
                $form->add(new Form_Dropdown('task_assign_to_0', __('Also assigned to'), array_flip($ass_also), '__select_person__'));
            }
        }

        foreach($tasks as $t) {
            $form->add(new Form_HiddenField("tasks_{$t->id}_chk",'',1));
        }
        $form->add(new Form_HiddenField('different_ass','',$different_assignments));
        #$form->add(new Form_HiddenField('task_project','',$project->id));

        echo($form);

        $PH->go_submit= 'taskEditMultipleSubmit';
        if($return=get('return')) {
            echo "<input type=hidden name='return' value='$return'>";
        }
    }

    echo (new PageContentClose);
    echo (new PageHtmlEnd);

    exit();
}



/**
* Submit changes to multiple tasks
*
* @ingroup pages
*/
function taskEditMultipleSubmit()
{
    global $PH;

    $ids= getPassedIds('tsk','tasks_*');

    if(!$ids) {
        $PH->abortWarning(__("Select some task(s) to mark as approved"), ERROR_NOTE);
        return;
    }

    $count=0;
    $errors=0;
    $number = get('number');

    ### cancel? ###
    if(get('form_do_cancel')) {
        if(!$PH->showFromPage()) {
            $PH->show('home');
        }
        exit();
    }

    foreach($ids as $id) {
        if($task= Task::getEditableById($id)) {
            $count++;
            $change= false;;

            $status_old= $task->status;


            ### status ###
            if($count == 1){
                if(!$project = Project::getVisibleById($task->project)) {
                    $PH->abortWarning('could not get project');
                }

                $team= array();
                foreach($project->getPeople() as $p) {
                    $team[$p->id]= $p;
                }
            }

            ### assignment ###
            {
                $task_assigned_people = array();
                $task_assignments = array();
                $task_people_overwrite = array();
                $task_people_new = array();
                $task_people_delete = array();

                ## previous assigend people ##
                if($task_people = $task->getAssignedPeople(false))
                {
                    foreach($task_people as $tp){
                        $task_assigned_people[$tp->id] = $tp;
                    }
                }

                ## previous assignements ##
                if($task_assign = $task->getAssignments())
                {
                    foreach($task_assign as $ta){
                        $task_assignments[$ta->person] = $ta;
                    }
                }

                ## different assigned people ##
                ## overwrite ?? ##
                $ass1 = get('task_assignement_diff');
                if($ass1 && $ass1 != '__dont_change__'){
                    $task_people_overwrite[] = $ass1;
                    foreach($task_assignments as $key=>$value){
                        $task_people_delete[] = $value;
                    }
                    $change = true;
                }

                ## new ?? ##
                $ass2 = get('task_assignement_also_diff');
                if($ass2 && $ass2 != '__select_person__'){
                    $task_people_new[] = $ass2;
                    $change = true;
                }

                $different = get('different_ass');
                if(isset($different) && !$different){
                    if(isset($task_assignments) && count($task_assignments) != 0){
                        foreach($task_assignments as $tid=>$t_old){
                            $id_new = get('task_assign_to_'.$tid);
                            ## no changes ##
                            if($tid == $id_new){
                                continue;
                            }

                            if($id_new == '__none__'){
                                if(!$t_old){
                                    continue;
                                }
                                $task_people_delete[] = $t_old;
                                continue;
                            }

                            $task_people_delete[] = $t_old;
                            $task_people_overwrite[] = $id_new;
                        }
                    }
                    else{
                        $id_new = get('task_assign_to___none__');
                        if($id_new && $id_new != '__none__'){
                            $task_people_new[] = $id_new;
                        }
                    }

                    $id_new = get('task_assign_to_0');
                    if($id_new != '__select_person__'){
                        if(!isset($task_assignments[$id_new])){
                            $task_people_new[] = $id_new;
                        }
                    }

                    $change = true;
                }
            }


            ### category ###
            $v= get('task_category');
            if(!is_null($v) && $v != '__dont_change__' && $v != $task->category) {
                $task->category= $v;
                $change= true;
            }


            ### status ###
            $status= get('task_status');
            if($status && $status != '__dont_change__' && $status != $task->status) {
                $task->status= $status;
                $change= true;
            }

            ### prio ###
            $prio= get('task_prio');
            if($prio && $prio != '__dont_change__' && $prio != $task->prio) {
                $task->prio= $prio;
                $change= true;
            }

            ### pub level ###
            $pub_level= get('task_pub_level');
            if($pub_level && $pub_level != '__dont_change__' && $pub_level != $task->pub_level) {


               if($pub_level > $task->getValidUserSetPublicLevel() ) {
                   $PH->abortWarning('invalid data',ERROR_RIGHTS);
               }
               $task->pub_level= $pub_level;
               $change= true;
            }

            ### label ###
            $label= get('task_label');
            if($label && $label != '__dont_change__' && $label != $task->label) {
                $task->label= $label;
                $change= true;
            }

            ### for milestone ###
            $fm= get('task_for_milestone');
            if(!is_null($fm) && $fm != '__dont_change__' && $task->for_milestone != $fm) {
                if($fm) {
                    if(($m= Task::getVisibleById($fm)) && $m->isMilestoneOrVersion()) {
                        $task->for_milestone= $fm;
                        $change= true;
                    }
                    else {
                        continue;
                    }
                }
                else {
                    $task->for_milestone= 0;
                    $change= true;
                }
            }

            ### resolve version ###
            $rv= get('task_resolved_version');

            if((!is_null($rv)) && ($rv != '__dont_change__') && ($task->resolved_version != $rv)) {
                if($rv && $rv != -1) {
                    if($v= Task::getVisibleById($rv)) {
                        if($v->isMilestoneOrVersion()) {
                            $task->resolved_version= $rv;
                            $change= true;
                        }
                    }
                    else {
                        continue;
                    }
                }
                else {
                    if($rv == -1) {
                        $task->resolved_version= $rv;
                        $change= true;
                    }
                    else {
                        $task->resolved_version= 0;
                        $change= true;
                    }
                }
            }

            ### resolve reason ###
            $rs= get('task_resolve_reason');
            if($rs && $rs != '__dont_change__' && $rs != $rs->resolve_reason) {
                $task->resolve_reason= $rs;
                $change= true;
            }


            if($change) {

                ### Check if now longer new ###
                if($status_old == $task->status && $task->status == STATUS_NEW) {
                    global $auth;
                    if($task->created < $auth->cur_user->last_login) {
                        $task->status = STATUS_OPEN;
                    }
                }
                ## overwrite assigend people ##
                if(isset($task_people_overwrite)){
                    if(isset($task_people_delete)){
                        foreach($task_people_delete as $tpd){
                            $tpd->delete();
                            
                        }
                    }
                    foreach($task_people_overwrite as $tpo)
                    {
                        $task_pers_over = new TaskPerson(array(
                                        'person'=> $team[$tpo]->id,
                                        'task'  => $task->id,
                                        'comment'=>'',
                                        'project'=>$project->id,
                                        ));
                        $task_pers_over->insert();
                    }
                }

                ## add new person ##
                if(isset($task_people_new)){
                    foreach($task_people_new as $tpn){
                        if(!isset($task_assigned_people[$tpn]))
                        {
                            $task_pers_new = new TaskPerson(array(
                                         'person'=> $team[$tpn]->id,
                                         'task'  => $task->id,
                                         'comment'=>'',
                                         'project'=>$project->id,
                                          ));
                           $task_pers_new->insert();
                        }
                    }

                }

                ##update##
                $task->update();
                $task->nowChangedByUser();
            }
        }
        else {
            $errors++;
        }
    }

    ### compose message ###
    if($errors) {
        new FeedbackWarning(sprintf(__('%s tasks could not be written'), $errors));
    }
    else if($count) {
        new FeedbackMessage(sprintf(__('Updated %s tasks tasks'), $count));
    }

    ### return to from-page? ###
    if(!$PH->showFromPage()) {
        $PH->show('taskView',array('tsk'=>$task->id));
    }

}






/**
* Collapse all comments of a tasks
*
* @ingroup pages
*/
function taskCollapseAllComments()
{
    global $PH;


    /**
    * because there are no checkboxes left anymore, we have to get the comment-ids
    * directly from the task with the getComments-function
    **/
    ### get task ###
    $tsk=get('tsk');

    ### check sufficient user-rights ###
    if($task=Task::getEditableById($tsk)) {
        $ids= $task->getComments();

        foreach($ids as $obj) {
            if(!$comment=Comment::getEditableById($obj->id)) {
                $PH->abortWarning('undefined comment','warning');
            }
            if(! $comment->view_collapsed) {
                $comment->view_collapsed=1;
                $comment->update();
            }
        }
    }
    else {
        /**
        * a better way should be not to display this function
        * if user has not enough rights
        **/
        ### abort, if not enough rights ###
        $PH->abortWarning(__('insufficient rights'),ERROR_RIGHTS);
    }

    ### display taskView ####
    if(!$PH->showFromPage()) {
        $PH->show('home');
    }
}

/**
* Expand all comments of a task
*
* @ingroup pages
*/
function taskExpandAllComments()
{
    global $PH;

    /**
    * because there are no checkboxes left anymore, we have to get the comment-ids
    * directly from the task with the getComments-function
    **/
    ### get task ###
    $tsk= get('tsk');

    ### check sufficient user-rights ###
    if($task=Task::getEditableById($tsk)) {
        $ids= $task->getComments();

        foreach($ids as $obj) {
            if(!$comment=Comment::getEditableById($obj->id)) {
                $PH->abortWarning('undefined comment','warning');
            }

            ### get all comments including all sub-comments
            $list= $comment->getAll();
            $list[]= $comment;

            foreach($list as $c) {
                if($c->view_collapsed) {
                    $c->view_collapsed=0;
                    $c->update();
                }
            }
        }
    }
    else {
        /**
        * a better way should be not to display this function
        * if user has not enough rights
        **/
        ### abort, if not enough rights ###
        $PH->abortWarning(__('insufficient rights'),ERROR_RIGHTS);
    }

    ### display taskView ####
    if(!$PH->showFromPage()) {
        $PH->show('home');
    }
}

/**
* create new note on person
*
* @ingroup pages
*/
function taskNoteOnPersonNew()
{
    global $PH;

    ## get person ##
    $pid = getOnePassedId('person');
    if(!$person = Person::getById($pid)){
        $PH->abortWarning(__("ERROR: could not get Person"), ERROR_NOTE);
        return;
    }

    ## build new object ##
    $task_new = new Task(array(
            'id'        =>0,        # temporary new
            'name'      =>'',
            'state'     =>1,
             ));

    $PH->show('taskNoteOnPersonEdit',array('tsk'=>$task_new->id, 'person'=>$person->id), $task_new);
}

/**
* Edit note on person
*
* @ingroup pages
*/
function taskNoteOnPersonEdit($task=NULL, $person=NULL)
{
    global $PH;
    global $auth;
    global $g_pub_level_names;
    global $g_prio_names;

    if(!$task){
        $id = getOnePassedId('tsk');

        if(!$task = Task::getEditableById($id)) {
            $PH->abortWarning(__("Select a note to edit"), ERROR_NOTE);
            return;
        }
    }

    ## get person ##
    if(!$person){
        $pid = getOnePassedId('person');
        if(!$person = Person::getById($pid)) {
            $PH->abortWarning(__("ERROR: could not get Person"), ERROR_NOTE);
            return;
        }
    }

    ### set up page and write header ####
    {
        $page = new Page(array('use_jscalendar'=>false, 'autofocus_field'=>'task_name'));
        $page->cur_tab = 'people';

        if($person->id) {
            $page->crumbs=build_person_crumbs($person);
        }
        $page->crumbs[]=new NaviCrumb(array(
            'target_id' => 'taskNoteOnPersonEdit',

        ));

        $page->type=__("Note");

        if(!$task->id) {
            $page->title = __('Create new note');
            $page->title_minor=__('Edit');
            ## default title ##
            $date = gmdate("Y-m-d", time());
            $time = getGMTString();
            $dt = $date . " " . renderTime($time);
            $task->name = sprintf(__("New Note on %s, %s"),$person->name, $dt);
        }
        ## eventually needed later when note is a subcategory of task
        /*else {
            $page->title=$task->name;
            $page->title_minor=$task->short;
        }*/

        echo(new PageHeader);
    }

    echo (new PageContentOpen);

    ### write form #####
    {
        require_once(confGet('DIR_STREBER') . 'render/render_form.inc.php');

        $form = new PageForm();
        $form->button_cancel=true;

        ## name field ##
        $form->add($task->fields['name']->getFormElement($task));

        ## description field ##
        $e = $task->fields['description']->getFormElement($task);
        $e->rows = 22;
        $form->add($e);

        ### public-level drop down menu ###
        $form->add(new Form_Dropdown('task_pub_level',  __("Publish to","Form label"), array_flip($g_pub_level_names), $task->pub_level));

        ## priority drop down menu##
        $form->add(new Form_Dropdown('task_prio',  __("Prio","Form label"),  array_flip($g_prio_names), $task->prio));

        ## project drop down menu ##
        {
            if($task->id == 0){
                $proj_select = 0;
            }
            
            $p_list = array();

            $count = 1;

            $p_projects = $person->getProjects();
            $num = count($p_projects);

            if($num > 0){
                $p_list[0] = __('Assigned Projects');
                foreach($p_projects as $pp){
                    $p_list[$pp->id] = "- " . $pp->name;
                    $count++;
                }
            }


            $p_companies = $person->getCompanies();
            $num = count($p_companies);

            if($num > 0){
                $p_list['-1'] = __('Company Projects');
                foreach($p_companies as $pcs){
                    $c_id = $pcs->id;
                    $c_projects = Project::getAll(array('company'=>$c_id));
                    $count2 = 0;
                    foreach($c_projects as $cp){
                        $p_list[$cp->id] = "- " . $cp->name;
                    }

                }
            }

            if(!$projects = Project::getAll(array('order_by'=>'name ASC'))){
            }
            else{
                $p_list['-2'] = __('All other Projects');
                foreach($projects as $pj){
                    $p_list[$pj->id] = "- " . $pj->name;
                }
            }

            $form->add(new Form_Dropdown('project',  __('For Project','form label'),array_flip($p_list), $proj_select, "id='proj_list'"));
        }

        ## new project ##
        if($task->id == 0){
            $form->add(new Form_checkbox('new_project',__('New project','form label'), false, "id='proj_new_checkbox'"));
            $form->add(new Form_Input('new_project_name', __('Project name', 'form label'),false,NULL,false, "id='proj_new_input'","style='display:none'"));
        }

        ## Assignements ##
        {
            $checked1 = "";
            $checked2 = "";

            if($task->id == 0){
                $checked1 = "checked";
                $checked2 = "checked";
                $person_select = -1;
            }
            ## eventually needed later when note is a subcategory of task
            /*else {
                if(!$pperson = $task->getAssignedPeople()){
                    $PH->abortWarning(__("ERROR: could not get assigned people"), ERROR_NOTE);
                }
                else{
                    foreach($pperson as $pp){
                        if($pp->id == $person->id){
                            $checked1= "checked";
                        }
                        elseif($pp->id == $auth->cur_user->id){
                            $checked2= "checked";
                        }
                        else{
                            $person_select = $pp->id;
                        }
                    }
                }
            }*/

            $form->add(new Form_customHTML('<p><label>' . __('Assign to') . '</lable></p>', 'assigne_note'));
            if($person->id != $auth->cur_user->id){
                $form->add(new Form_customHTML('<span class="checker"><input value="'.$person->id.'" name="task_assignement1" type="checkbox" ' . $checked1 .'><label for="task_assignement1">' . $person->name . '</label></span>', 'assigned_person1'));
                $form->add(new Form_customHTML('<span class="checker"><input value="'.$auth->cur_user->id.'" name="task_assignement2" type="checkbox" ' . $checked2 .'><label for="task_assignement2">' . $auth->cur_user->name . '</label></span>', 'assigned_person2'));
            }
            else {
                $form->add(new Form_customHTML('<span class="checker"><input value="'.$auth->cur_user->id.'" name="task_assignement2" type="checkbox" ' . $checked2 .'><label for="task_assignement2">' . $auth->cur_user->name . '</label></span>', 'assigned_person'));
            }

            $pers_list = array();
            $pers_list[-1] = __('undefined');
            if($people = Person::getPeople(array('can_login'=>1))){
                foreach($people as $pers){
                    if($auth->cur_user->name <> $pers->name) {
                        $pers_list[$pers->id] = $pers->name;
                    }
                }
            }

            $form->add(new Form_Dropdown('task_also_assign',  __('Also assign to') ,array_flip($pers_list), $person_select));
        }

        ## Book effort after submit ##
        $form->form_options[] = "<span class=option><input id='book_effort' name='book_effort' class='checker' type=checkbox>" . __("Book effort after submit") . "</span>";

        $form->add(new Form_HiddenField('tsk','',$task->id));
        $form->add(new Form_HiddenField('person_id','',$person->id));
        $form->add(new Form_HiddenField('creation_time','',$time));

        echo ($form);

        $PH->go_submit = 'taskNoteOnPersonEditSubmit';
    }
    echo (new PageContentClose);
    echo (new PageHtmlEnd);
}


/**
* Submit changes to notes on a person
*
* @ingroup pages
*/
function taskNoteOnPersonEditSubmit()
{
    global $PH;
    global $auth;
    global $g_user_profile_names;

    ### cancel? ###
    if(get('form_do_cancel')) {
        if(!$PH->showFromPage()) {
            $PH->show('personView',array('person'=>getOnePassedId('person_id')));
        }
        exit();
    }

    ### temporary object or from database? ###
    $tsk_id = getOnePassedId('tsk','',true,'invalid id');
    if($tsk_id == 0) {
        $task = new Task(array(
               'id'=>0,
               ));
    }
    ## eventually needed later when note is a subcategory of task
    /*else {
        if(!$task= Task::getVisiblebyId($tsk_id)) {
            $PH->abortWarning(__("ERROR: could not get task"), ERROR_NOTE);
            return;
        }
    }*/

    ## other parameter ##
    $person_id = getOnePassedId('person_id');
    $prj_id = get('project');
    $prj_new = get('new_project');
    $prj_name = get('new_project_name');
    $assignement1 = get('task_assignement1');
    $assignement2 = get('task_assignement2');
    $also_assignement = get('task_also_assign');

    ### pub level ###
    if($pub_level = get('task_pub_level')) {
        if($task->id) {
             if($pub_level > $task->getValidUserSetPublicLevels() ) {
                 $PH->abortWarning('invalid data',ERROR_RIGHTS);
             }
        }
        #else {
        #    #@@@ check for person create rights
        #}
        $task->pub_level = $pub_level;
    }

    ## prio ##
    if($prio = get('task_prio')){
        $task->prio = $prio;
    }

    ## status ##
    if(!$task->id){
        $task->status = STATUS_NEW;
    }

    # retrieve all possible values from post-data (with field->view_in_forms == true)
    # NOTE:
    # - this could be an security-issue.
    # @@@ TODO: as some kind of form-edit-behaviour to field-definition
    foreach($task->fields as $f) {
        $name=$f->name;
        $f->parseForm($task);
    }

    ### validate ###
    $is_ok = true;

    ## no project ##
    if(($prj_id <= 0)) {
        if(((!isset($prj_new)) || (!isset($prj_name)))){
            new FeedbackWarning(__("Note requires project"));

            ## and no assignement ##
            if((!isset($assignement1) && !isset($assignement2) && $also_assignement == -1)) {
                new FeedbackWarning(__("Note requires assigned person(s)"));
            }
            $is_ok= false;
        }
    }

    ## if project but no assignement ##
    if((!isset($assignement1) && !isset($assignement2) && $also_assignement == -1)) {
        $assignement1 = $auth->cur_user->id;
    }

    if(!$is_ok) {
        $PH->show('taskNoteOnPersonEdit',array('tsk'=>$task->id, 'person'=>$person_id), $task);
        exit();
    }

    ## new project
    if(isset($prj_new) && isset($prj_name)){
        $pperson = Person::getById($person_id);
        if($companies = $pperson->getCompanies()) {
            $company_id= $companies[0]->id;
        }
        else {
            $company_id= 0;
        }

        $new_project = new Project(array(
                       'name'=>$prj_name,
                       'company'=>$company_id,
                       'status'=>STATUS_NEW,
                       'prio'=>PRIO_NORMAL,
                       'pub_level'=>PUB_LEVEL_OPEN
                     ));
        $new_project->insert();
        $prj_id = $new_project->id;
        ## get project ##
        if(!$project = Project::getById($prj_id)){
             $PH->abortWarning(__("ERROR: could not get project"), ERROR_NOTE);
        }
    }
    else {
        ## get project ##
        if(!$project = Project::getById($prj_id)){
             $PH->abortWarning(__("ERROR: could not get project"), ERROR_NOTE);
        }
    }

    ## set project of task ##
    if(!$task->id){
        $task->project = $project->id;
    }

    ## assigne people to task##
    $new_task_assignments = array();
    $count = 0;
    if(!$task->id){
        if(isset($assignement1)){
            $person = Person::getById($assignement1);
            $new_assignment1 = new TaskPerson(array(
                              'person'=> $assignement1,
                              'task'  => $task->id,
                              'comment'=>sprintf(__("formerly assigned to %s","task-assigment comment"), $person->name),
                              'project'=>$project->id,
                              ));
            $new_task_assignments[$count] = $new_assignment1;
            $count++;
        }

        if(isset($assignement2)){
            $person = Person::getById($assignement2);
            $new_assignment2 = new TaskPerson(array(
                              'person'=> $assignement2,
                              'task'  => $task->id,
                              'comment'=>sprintf(__("formerly assigned to %s","task-assigment comment"), $person->name),
                              'project'=>$project->id,
                              ));
            $new_task_assignments[$count] = $new_assignment2;
            $count++;
        }

        if($also_assignement != -1){
            $person = Person::getById($also_assignement);
            $new_assignment_also = new TaskPerson(array(
                                 'person'=> $also_assignement,
                                 'task'  => $task->id,
                                 'comment'=>sprintf(__("formerly assigned to %s","task-assigment comment"), $person->name),
                                 'project'=>$project->id,
                                 ));
            $new_task_assignments[$count] = $new_assignment_also;
            $count++;
        }
    }
    ## eventually needed later when note is a subcategory of task
    /*else {
        # ToDo: check if people are assigned
    }*/

    ## assigne person to project ##
    $team = array();
    $new_project_assignments = array();
    $count = 0;
    if(!$task->id){
        $projperson = $project->getPeople(false);
        foreach($projperson as $projp){
            $team[$projp->id] = $projp->name;
        }

        if(isset($assignement1)){
            if(!isset($team[$assignement1])){
                $person = Person::getById($assignement1);
                $effort_style = ($person->settings & USER_SETTING_EFFORTS_AS_DURATION)
                              ? 2
                              : 1;
                $pp_new1 = new ProjectPerson(array(
                    'person'                => $person->id,
                    'project'               => $project->id,
                    'name'                  => $g_user_profile_names[$person->profile],
                    'adjust_effort_style'   => $effort_style,
                ));
                $new_project_assignments[$count] = $pp_new1;
                $count++;
            }
        }

        if(isset($assignement2)){
            if(!isset($team[$assignement2])){
                $effort_style = ($person->settings & USER_SETTING_EFFORTS_AS_DURATION)
                              ? 2
                              : 1;
                $person = Person::getById($assignement2);
                $pp_new2 = new ProjectPerson(array(
                    'person'                => $person->id,
                    'project'               => $project->id,
                    'name'                  => $g_user_profile_names[$person->profile],
                    'adjust_effort_style'   => $effort_style,
                ));
                $new_project_assignments[$count] = $pp_new2;
                $count++;
            }
        }
        if($also_assignement != -1){
            if(!isset($team[$also_assignement])){
                $person = Person::getById($also_assignement);
                $effort_style = ($person->settings & USER_SETTING_EFFORTS_AS_DURATION)
                              ? 2
                              : 1;
                $pp_new_also = new ProjectPerson(array(
                    'person'                => $person->id,
                    'project'               => $project->id,
                    'name'                  => $g_user_profile_names[$person->profile],
                    'adjust_effort_style'   => $effort_style,
                ));
                $new_project_assignments[$count] = $pp_new_also;
                $count++;
            }
        }

    }
    ## eventually needed later when note is a subcategory of task
    /*else{
        # ToDo: check if people are assigned
    }*/



    ## Insert ##
    if($task->id == 0) {
        $task->insert();

        ### write task-assigments ###
        foreach($new_task_assignments as $nta) {
            $nta->task = $task->id;
            $nta->insert();
        }

        ### write project-assigments ###
        foreach($new_project_assignments as $npa) {
            $npa->insert();
        }

        new FeedbackMessage(sprintf(__("Created task %s with ID %s"),  $task->getLink(false),$task->id));
    }
    ## eventually needed later when note is a subcategory of task
    /*
    else{
    }
    */

    ### book effort ###
    $book_effort = get('book_effort');
    if($book_effort) {
        $as_duration = 0;
        if($pperson = $project->getProjectPeople()){
            foreach($pperson as $pp){
                if(($pp->project == $project->id) && ($pp->person == $auth->cur_user->id)){
                    if($pp->adjust_effort_style == 1){
                        $as_duration = 0;
                    }
                    else{
                        $as_duration = 1;
                    }
                }
            }
        }
        else{
            $as_duration = 0;
        }

        if(get('creation_time')){
            $start_time = get('creation_time');
        }
        else{
            $start_time = '';
        }

        ### build new object ###
        $newEffort= new Effort(array(
            'id'        =>0,
            'name'      =>'',
            'project'   =>$project->id,
            'task'      =>$task->id,
            'person'    =>$auth->cur_user->id,
            'as_duration' =>$as_duration,
            'time_start' =>$start_time,
            )
        );
        $PH->show('effortEdit',array('effort'=>$newEffort->id),$newEffort);
        exit();
    }

    ### display personList ####
    if(!$PH->showFromPage()) {
        $PH->show('personList',array());
    }

}

/** @} */

?>
