<?php

/**
 * Web service methods to support gadgets
 */
class BuddyWebService implements WebServiceable {

    /**
     * Something that silverstripe imposes!
     */
    public function __construct() {

    }

    public function webEnabledMethods() {
        return array(
            'getInitialData' => 'GET',
            'getInvites' => 'GET',
            'getBuddies' => 'GET',
            'getUnreadMessages' => 'GET',
            'getThreads' => 'GET',
            'getProfile' => 'GET',
            'makeRead' => 'GET',
            'replyThread' => 'GET',
            'newThread' => 'GET'
        );
    }

    /**
     * Data required by gadget on initial loading
     * Returns:
     * buddies = array of buddy data from getBuddies (not exists if error)
     */
    public function getInitialData() {
       $retarray = array();
       $buddies = $this->getBuddies();
       if (!isset($buddies['error'])) {
           $retarray['buddies'] = $buddies;
       }
       return $retarray;
    }

    /**
     * Gets all buddies for the member
     * Returns array, element for each buddy with:
     * name = Buddy name
     * ID = Buddy user ID
     * Avatar = url to avatar image
     */
    public function getBuddies() {
        $memid = GadgetWebServiceController::getGadgetMemberID();
        if (is_null($memid)) {
            //Should never have this situation as should of already failed
            return array(
                'message' => 'No valid user for request.',
                'error' => 'Error returning number of invites.'
            );
        }
        $retarray = array();
        $buddies = Buddy::getMemberBuddies($memid);//Already sorted by name
        foreach ($buddies as $buddy) {
            //Work out who is buddy in rec - initiator or buddy member
            if ($buddy->InitiatorID == $memid) {
                //Buddy is Buddy
                $thebuddy = Member::get_by_id('Member', $buddy->BuddyID);
            } else {
                //Buddy is Initiator
                $thebuddy = Member::get_by_id('Member', $buddy->InitiatorID);
            }
            $buddyrec = array();
            $buddyrec['name'] = $thebuddy->getName();
            $buddyrec['id'] = $thebuddy->ID;
            //WARNING - THIS WILL ALWAYS SHOW THEIR AVATAR - DOESN'T CHECK PROFILE SETTINGS
            $imageobj = $thebuddy->getBuddyAvatarImage();
            $url = Director::absoluteURL($imageobj->FileName ,true);
            $url = str_ireplace('index.php/', '', $url);
            $buddyrec['avatar'] = $url;
            $retarray[] = $buddyrec;//add this buddy to returned array
        }
        return $retarray;
    }

    /**
     * Get the number of Invites the user needs to respond to
     * Returns:
     * result = number of unresponded invitations (defaults to 0 on error)
     * url = adress to show the invites
     * message = any error msg
     * @return int
     */
    public function getInvites() {
        $memid = GadgetWebServiceController::getGadgetMemberID();
        if (is_null($memid)) {
            //Should never have this situation as should of already failed
            return array(
                'result' => 0,
                'message' => 'No valid user for request.',
                'error' => 'Error returning number of invites.'
            );
        }
        $buddyinvites = buddy::getMemberToResponds($memid);
        $numbuddies = count($buddyinvites);
        $url = Director::absoluteURL( singleton('Buddies')->Link(), true);
        return array(
            'result' => $numbuddies,
            'url' => $url,
            'message' => '',
        );
    }

    //Get all the unread messages by thread, order thread by most recent message
    /*return assoc array of:
    * message - any error message, empty if none
    * total - total num of unread messages
    * threads - array of threads that contain unread messages
    *    id - id of thread
    *    title - thread title
    *    buddy - Name of the buddy that the thread is with
    *    buddyid - id of buddy
    *    avatar - url of buddy avatar image (see other methods)
    *    messages - array of unread messages in the thread
    *        id - message id
    *        date - date message sent in Nice() format
    *        text - message text
    *        unread - flag if unread
    *        buddy - Name of sender
    *        buddyid - id of sender
    *        avatar - avatar of sender
    */
    public function getUnreadMessages() {
        $threads = $this->getThreads(null, 0, true);
        if (isset($threads['threads'])) {
            $threads['total'] = count($threads['threads']);
        }
        return $threads;
    }

    /*Get all message threads - order by most recent
     * - optional filter to show only with a specific buddy
     * TODO - if time, add limit e.g. 20 threads + pagination (get from thread 20 etc)
     * Will need to return assoc array of:
    * message - any error message, empty if none
    * threads - array of threads that contain unread messages
    * TODO - limit number of messages returned per thread + add method to get more
    *    id - id of thread
    *    title - thread title
    *    buddy - Name of the buddy that the thread is with
    *    buddyid - id of buddy
    *    avatar - url of buddy avatar image (see other methods)
    *    unread - flag if unread
    *    messages - array of unread messages in the thread
    *        id - message id
    *        date - date message sent in Nice() format
    *        text - message text
    *        buddy - Name of sender
    *        buddyid - id of sender
    *        avatar - avatar of sender
    */
    public function getThreads($buddyid = null, $start = 0, $unread = false) {
        if ($buddyid == 0) {
            $buddyid = null;//make sure is null if not set
        }
        $memid = GadgetWebServiceController::getGadgetMemberID();
        if (is_null($memid)) {
            //Should never have this situation as should of already failed
            return array(
                'message' => 'No valid user for request.',
                'error' => 'Error returning messages.'
            );
        }
        //Set current session to memberid
        //$session = new Session();
        //$session->start();
        //$session->set('loggedInAs', $memid);
        //FIRST - get all the threads for our user
        $filter = "`Deleted` != 1";
        if ($unread) {
            $filter .= " AND `IsRead` != 1";
        }
        $curmem = DataObject::get_by_id('Member', $memid);
        $query = $curmem->getManyManyComponentsQuery(
            'Threads',
            $filter,
            null,
            "INNER JOIN `Message` ON `Message`.ThreadID = `Thread`.ID"
        );

        // Save these fields as unique aliases so we don't call them twice {@see Thread::IsRead()}
        $query->select[] = "`Member_Threads`.IsRead AS CacheIsRead";
        $query->select[] = "`Member_Threads`.Deleted AS CacheDeleted";

        $query->select[] = "MAX(`Message`.Created) AS LatestMessageDate";
        $query->orderby("LatestMessageDate DESC");

        //TODO - support pagination of threads
        $limit = $start.",". 25;
        //$query->limit($limit);

        $threads = singleton("Thread")->buildDataObjectSet($query->execute(), 'DataObjectSet', $query, 'Thread');

        if (!$threads) {
            //No messages found
            return array('threads' => array());
        }

        //SECOND - We have list of threads user is in - now work out other user(s)
        $ids = implode(",", $threads->column("ID"));
        $query = new SQLQuery();
        $query->select("MemberID, ThreadID");
        $query->from("Member_Threads");
        $query->where(array("ThreadID IN ($ids)", "MemberID != $memid"));
        $othermem = $query->execute();

        //THIRD - As thread list is aggregated to order results we must then get messages
        $query = new SQLQuery();
        $query->select("Message.*");
        $query->from("Message");
        $query->where(array("ThreadID IN ($ids)"));
        $query->orderby("ThreadID, Created DESC");
        //TODO - limit messages returned per thread, possible - needs multi select?
        $messages = singleton("Message")->buildDataObjectSet($query->execute(), 'DataObjectSet', $query, 'Message');

        //FORTH - Build up everything from all the data collected
        $retarray = array();
        $threadsarr = array();
        foreach($threads as $thread) {
            $threadarr = array(
                'id' => $thread->ID,
                'title' => htmlspecialchars($thread->Subject),
                'unread' => $thread->CacheIsRead == 0 ? true: false,
            );
            //Find buddy info - work out other buddy
            $buddyidlist = array();
            foreach($othermem as $record) {
                if($record['ThreadID'] == $thread->ID) {
                    $buddyidlist[] = $record['MemberID'];
                }
            }
            //ONLY SUPPORT 1 OTHER USER IN A THREAD - pick first
            if (isset($buddyidlist[0])) {
                if (!is_null($buddyid)) {
                    if ($buddyid != $buddyidlist[0]) {
                        continue;//Skip this thread as not with chosen buddy
                    }
                }
                $info = self::getMemberInfo($buddyidlist[0]);
                $threadarr['buddy'] = $info['buddy'];
                $threadarr['buddyid'] = $info['id'];
                $threadarr['avatar'] = $info['avatar'];
            } else {
                //Catch if not with a buddy
                if (!is_null($buddyid)) {
                    continue;//Must be with chosen buddy
                }
                //As a fallback just use self info
                $info = self::getMemberInfo($memid);
                $threadarr['buddy'] = $info['buddy'];
                $threadarr['buddyid'] = $info['id'];
                $threadarr['avatar'] = $info['avatar'];
            }
            //Now add messages
            $messagesarr = array();
            foreach($messages as $message) {
                if ($message->ThreadID == $thread->ID) {
                    $msg = array(
                        'id' => $message->ID,
                        'date' => $message->obj('Created')->Nice(),
                        'text' => htmlspecialchars($message->Body)
                    );
                    $info = self::getMemberInfo($message->AuthorID);
                    $msg['buddy'] = $info['buddy'];
                    $msg['buddyid'] = $info['id'];
                    $msg['avatar'] = $info['avatar'];
                    $messagesarr[] = $msg;
                }
            }
            $threadarr['messages'] = $messagesarr;
            $threadsarr[] = $threadarr;
        }
        $retarray['threads'] = $threadsarr;
        return $retarray;
    }

    private static $memberinfo = array();
    /*
     * Will get info on the member - checks in static for a cache to minimise db calls
     * @return array
     */
    private static function getMemberInfo($memid) {
        foreach(self::$memberinfo as $member => $info) {
            if ($info['id'] == $memid) {
                return $info;
            }
        }
        $member = DataObject::get_by_id('Member', $memid);
        $info = array(
            'id' => $member->ID,
            'buddy' => $member->getName(),
        );
        //WARNING - THIS WILL ALWAYS SHOW THEIR AVATAR - DOESN'T CHECK PROFILE SETTINGS
        $imageobj = $member->getBuddyAvatarImage();
        $url = Director::absoluteURL($imageobj->FileName ,true);
        $url = str_ireplace('index.php/', '', $url);
        $info['avatar'] = $url;
        self::$memberinfo[] = $info;
        return $info;
    }

    /**
     * Makes a thread read
     * @param $threadid
     */
    public function makeRead(int $threadid) {
        $memid = GadgetWebServiceController::getGadgetMemberID();
        if (is_null($memid)) {
            //Should never have this situation as should of already failed
            return array(
                'message' => 'No valid user for request.',
                'error' => 'Error making message read.'
            );
        }
        $result = DB::query("UPDATE `Member_Threads` SET IsRead = 1 WHERE MemberID = $memid AND ThreadID = $threadid");
        if ($result) {
            return array();
        } else {
            return array(
                'message' => 'No valid data for request.',
                'error' => 'Error making message read.'
            );
        }
    }

    /**
     * TODO Create a new message thread - should check buddy relationship if not done anyway
     */
    public function newThread(int $buddyid, $title, $message) {
        $memid = GadgetWebServiceController::getGadgetMemberID();
        if (is_null($memid)) {
            //Should never have this situation as should of already failed
            return array(
                'message' => 'No valid user for request.',
                'error' => 'Error making message.'
            );
        }
        if (Buddy::getAreBuddies($memid, $buddyid)) {
            $thread = new Thread();
            $thread->Subject = Convert::raw2sql($title);
            $thread->write();
            $member = DataObject::get_by_id("Member", Convert::raw2sql($memid));
            $member->Threads()->add($thread);
            $othermem = DataObject::get_by_id("Member", Convert::raw2sql($buddyid));
            $othermem->Threads()->add($thread);
            // Create the message
            $mess = new Message();
            $mess->Body = Convert::raw2sql($message);
            $mess->AuthorID = $memid;
            $mess->ThreadID = $thread->ID;
            $mess->write();
        } else {
            return array(
                'message' => 'No valid data for request.',
                'error' => 'Error making message - you are not buddies.'
            );
        }
    }

    //TODO reply to a message thread - should check buddy relationship if not done anyway
    public function replyThread(int $threadid, $message) {
        $memid = GadgetWebServiceController::getGadgetMemberID();
        if (is_null($memid)) {
            //Should never have this situation as should of already failed
            return array(
                'message' => 'No valid user for request.',
                'error' => 'Error replying.'
            );
        }
        //Check user is in thread/valid thread
        $query = new SQLQuery();
        $query->select("MemberID, ThreadID");
        $query->from("Member_Threads");
        $query->where(array("ThreadID = $threadid", "MemberID = $memid"));
        $threadresult = $query->execute();
        if ($threadresult->value()) {
            $mess = new Message();
            $mess->AuthorID = (int) $memid;
            $mess->Body = Convert::raw2sql($message);
            $mess->ThreadID = (int) $threadid;
            $mess->write();
        } else {
            return array(
                'message' => 'No valid data for request.',
                'error' => 'Bad thread ID.'
            );
        }
    }

    /**
     * Get profile information
     * Will check user is allowed to see if not id 0
     * Returns array of:
     * message - error message
     * link - will be set for current user profile to link to their online profile
     * name - when not current user
     * avatar - url of avatar (always assume it can be seen)
     * items - associative array of name, type, value (as per BuddyProfile page)
     * @param int $id 0 for current user
     */
    public function getProfile(int $id) {
        $memid = GadgetWebServiceController::getGadgetMemberID();
        if (is_null($memid)) {
            //Should never have this situation as should of already failed
            return array(
                'message' => 'No valid user for request.',
                'error' => 'Error returning profile.'
            );
        }
        $retarray = array();
        if ($id == 0) {
            //Current user's profile
            $member = Member::get_by_id('Member', $memid);
            $retarray['link'] = Director::absoluteURL( singleton('BuddyProfile')->Link(), true);
        } else {
            //check cur user is allowed to see
            try {
                if (!$member = DataObject::get_by_id('Member', $id)) {
                    throw new exception();
                }
                if(!Permission::check('VIEW_BUDDY_PROFILE', 'any', $member)) {
                    throw new exception();
                }
                //If not current user check what availability user has set (Buddies or open access)
                if (isset($member->BuddyPublicProfile) && $member->BuddyPublicProfile == false) {
                    //Are users Buddies? - if not don't show profile
                    if (!Buddy::getAreBuddies($member->ID, $memid,
                        Buddy::RELATIONSHIP_CONFIRMED)) {
                        throw new exception();
                    }
                }
            } catch(Exception $e) {
                return array(
                'message' => 'You are not allowed to view this profile.',
                'error' => 'Error returning profile.'
                );
            }
            $retarray['name'] = $member->FirstName;
        }
        $result = array();
        $buddyprofile = $member->getProfileStructure();
        foreach ($buddyprofile as $fieldname => $field) {
            //View type set?
            if ($field->vtype != null) {
                //Get data value, either from record or work out
                $value = null;
                if (!is_null($field->dbfield)) {
                    //Specific field identified
                    $dbfield = $field->dbfield;
                    $value = Convert::raw2xml($member->$dbfield);
                } else {
                    //Noting identified - is element name a field?
                    if (!empty($member->$fieldname)) {
                        $value = Convert::raw2xml($member->$fieldname);
                    }
                }
                //Different types might need to get value differently
                if (stripos($field->vtype, 'ManyMany') !== false) {
                    //Need to get many many values (using array element name)
                    //TODO - How can titles be multi-language?
                    $value = $member->getManyManyComponents($fieldname, null, 'Title ASC');
                }
                $result[] = array(
                    'name' => $field->name,
                    'type' => $field->vtype,
                    'value' => $value
                );
            }
        }
        $retarray['items'] = $result;
        //WARNING - THIS WILL ALWAYS SHOW THEIR AVATAR - DOESN'T CHECK PROFILE SETTINGS
        $imageobj = $member->getBuddyAvatarImage();
        $url = Director::absoluteURL($imageobj->FileName ,true);
        $url = str_ireplace('index.php/', '', $url);
        $retarray['avatar'] = $url;

        return $retarray;
    }
}
