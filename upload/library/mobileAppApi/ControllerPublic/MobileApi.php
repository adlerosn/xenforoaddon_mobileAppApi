<?php

class mobileAppApi_ControllerPublic_MobileApi extends XenForo_ControllerPublic_Abstract {
	public $fc;
	public static $apiVersion = 1;
	public function getApiMods(){
		return [];
	}
	protected function _preDispatch($action){
		//setting framework
		$this->_routeMatch->setResponseType('json');
		//making usable by mobile apps
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
		header('Access-Control-Allow-Headers: X-Requested-With,content-type');
		//json headers
		header('Content-Type: application/json; charset=utf-8');
		//decoding json input and incorporating to the request
		$phpInput = file_get_contents("php://input");
		if($phpInput){ //workaround to be able to send brackets in message
			try{
				//static::returnJson([false, $phpInput]);
				$decoded='';
				for($i=0;$i<strlen($phpInput);$i+=4){
					$bucket = substr($phpInput,$i,4);
					$char = json_decode('"\u'.$bucket.'"');
					$decoded.= $char;
				}
				//static::returnJson([false, $decoded]);
				$arrayPosted = json_decode($decoded,true);
				//static::returnJson([false, $arrayPosted]);
				if(getType($arrayPosted)==='array'){
					foreach($arrayPosted as $id=>$val){
						try{
							$this->_request->setParam(strval($id),strval($val));
						}catch(Exception $e){;}catch(Error $e){;}catch(ErrorException $e){;}
					}
				}
				//static::returnJson([false,$this->_input->filterSingle('cookie', XenForo_Input::STRING)]);
			}catch(Exception $e){;}catch(Error $e){;}catch(ErrorException $e){;}
		}
		//static::returnJson([false, $arrayPosted]);
		//setting user up
		$visitor = XenForo_Visitor::getInstance();
		$sessionObj = XenForo_Application::getSession();
		//* Saving previous session
		$previousSessionId = $sessionObj->getSessionId();
		$previousUserId = $visitor->user_id;
		//* Reading new session
		$data = $this->_input->filterSingle('cookie', XenForo_Input::STRING);
		if($data){
			$userId=0;
			$sessionId="";
			$cookieKey="";
			try{
				list($userId,$sessionId,$cookieKey) = json_decode($data);
			}catch(Exception $e){;}
			$userId = intval($userId);
			if($userId && $sessionId && $cookieKey){
				//* Checking received session
				$expiredSession = false;
				//** First: get the session
				$newSession = $sessionObj->getSessionFromSource($sessionId);
				if($newSession===false){
					$expiredSession = true;
					$newSession = [];
				}
				//** Then get the user from the session
				$newUserId = array_key_exists('user_id',$newSession)?$newSession['user_id']:0;
				//** Check if the cookie key belongs to the wanted user
				$keyMatches = ($cookieKey === $this->_getUserModel()->getUserRememberKeyForCookie($userId, null));
				//** Check if the wanted user is the user of the session
				$wantedUserMatches = ($userId === $newUserId);
				//** Veredictum to check if session will be changed
				$authorized = $keyMatches && $wantedUserMatches;
				if($authorized){
					$sessionSetup = new ReflectionMethod($sessionObj,'_setup');
					$sessionSetup->setAccessible(true);
					$sessionSetup->invoke($sessionObj,$sessionId);
					$newSessionId = $sessionObj->getSessionId();
					if($newSessionId===$sessionId){ //IP check passed and session set
						XenForo_Visitor::setup($userId);
					}else{ //IP check failed
						$sessionObj->delete(); //save space from database
						$sessionSetup->invoke($sessionObj,$previousSessionId);
						$this->impersonationDetected();
					}
				}else if($expiredSession){
					; //it happens
				}else{
					$this->impersonationDetected();
				}
			}
		}
		//XenForo_Visitor::setup(785);
		$visitor = XenForo_Visitor::getInstance();
		$unsetupVisitor = false;
		if(array_key_exists('user_deactivated_kiroraddon',$visitor->toArray())){
			if($visitor->toArray()['user_deactivated_kiroraddon']){
				$unsetupVisitor = true;
			}
		}
		if($visitor->toArray()['is_banned']){
			$unsetupVisitor = true;
		}
		if($unsetupVisitor){
			XenForo_Visitor::setup(0);
		}
		$visitor = XenForo_Visitor::getInstance();
		//getting front controller
		$this->fc = $this->_getClassInstanceFromBacktrace('XenForo_FrontController');
		//because no one develops good solutions without understanding the output
		//static::$prettyfiedJson = true;
		$this->_routeMatch->setResponseType('html');
		//running parent preDispatch actions
		return parent::_preDispatch($action);
	}
	public function impersonationDetected(){;}
	public static $prettyfiedJson = false;
	public static function returnJson($data = null){
		if(static::$prettyfiedJson){
			$data = json_encode($data,JSON_PRETTY_PRINT);
		}else{
			$data = json_encode($data);
		}
		die($data);
	}

	public static function debug($data = null){
		die(print_r($data,true));
	}

	public function actionError(){
		static::returnJson();
	}

	public function actionIndex(){
		$listed = get_class_methods($this);
		$methods = [];
		foreach($listed as $method){
			if(strpos($method,'action')===0){
				$methods[]=substr($method,6);
			}
		}
		$r = [
			'api_version'=>static::$apiVersion,
			'api_mods'=>$this->getApiMods(),
			'android_apk'=>null,
			'methods'=>$methods,
		];
		$r['android_apk'] = XenForo_Application::getOptions()->boardUrl.'/app.apk';
		$r['boardUrl'] = XenForo_Application::getOptions()->boardUrl.'';
		static::returnJson($r);
	}

	public function actionTermsOfService(){
		$data = (new XenForo_Phrase('terms_rules_text'))->__toString();
		static::returnJson($data);
	}

	public function _getClassInstanceFromBacktrace($class){
		foreach(debug_backtrace() as $backtrace){
			if(
				array_key_exists('object',$backtrace)
				&&
				$backtrace['class']===$class
			){
				return $backtrace['object'];
			}
		}
		static::returnJson("Couldn't find class ".$class." in backtrace.");
	}

	public function _getProtectedAttribute($obj, $prop) {
		$reflection = new ReflectionClass($obj);
		$property = $reflection->getProperty($prop);
		$property->setAccessible(true);
		return $property->getValue($obj);
	}

	public function _getBaseView(){
		return new XenForo_ViewPublic_Base(
			new XenForo_ViewRenderer_HtmlPublic(
				$this->_getProtectedAttribute($this->fc, '_dependencies'),
				$this->_getProtectedAttribute($this->fc, '_response'),
				$this->_getProtectedAttribute($this->fc, '_request')
			),
			$this->_getProtectedAttribute($this->fc, '_response'),
			[],
			'PAGE_CONTAINER'
		);
	}

	public function _getUserModel(){
		return $this->getModelFromCache('XenForo_Model_User');
	}
	public function _getLoginModel(){
		return $this->getModelFromCache('XenForo_Model_Login');
	}
	public function _getAlertModel(){
		return $this->getModelFromCache('XenForo_Model_Alert');
	}
	public function _getThreadModel(){
		return $this->getModelFromCache('XenForo_Model_Thread');
	}
	public function _getThreadPrefixModel(){
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}
	public function _getForumModel(){
		return $this->getModelFromCache('XenForo_Model_Forum');
	}
	public function _getPostModel(){
		return $this->getModelFromCache('XenForo_Model_Post');
	}
	public function _getSpamPreventionModel(){
		return $this->getModelFromCache('XenForo_Model_SpamPrevention');
	}
	public function _getThreadWatchModel(){
		return $this->getModelFromCache('XenForo_Model_ThreadWatch');
	}
	public function _getNodeModel(){
		return $this->getModelFromCache('XenForo_Model_Node');
	}
	public function _getChatModel(){
		return $this->getModelFromCache('Siropu_Chat_Model');
	}
	public function _getChatHelper(){
		return $this->getHelper('Siropu_Chat_Helper');
	}
	public function actionLogin($asMethod = false){
		$data = $this->_input->filter([
			'lgn' => XenForo_Input::STRING,
			'pwd' => XenForo_Input::STRING
		]);
		if($data['lgn']===''){
			static::returnJson([false,"Fields incomplete","Login is empty"]);
		}
		if($data['pwd']===''){
			static::returnJson([false,"Fields incomplete","Password is empty"]);
		}
		$userModel = $this->_getUserModel();
		$loginModel = $this->_getLoginModel();
		if($loginModel->requireLoginCaptcha($data['lgn'])){
			static::returnJson([false,"Too many failed attempts","Wait some minutes"]);
		}
		$userId = $userModel->validateAuthentication($data['lgn'], $data['pwd'], $error);
		if(!$userId){
			$loginModel->logLoginAttempt($data['lgn']);
			static::returnJson([false,"Wrong credentials",strval($error)]);
		}
		$loginModel->clearLoginAttempts($data['lgn']);
		$user = $this->_getUserModel()->getFullUserById($userId);
		$loginHelper = $this->getHelper('Login');
		$visitor = XenForo_Visitor::setup($userId);
		if(array_key_exists('user_deactivated_kiroraddon',$visitor->toArray())){
			if($visitor->toArray()['user_deactivated_kiroraddon']){
				static::returnJson([false,"Unauthorized","This account is deactivated"]);
			}
		}
		if($visitor->toArray()['is_banned']){
			static::returnJson([false,"Unauthorized","This member is banned"]);
		}
		$visitor = XenForo_Visitor::setup(0);
		if($asMethod){
			return $user;
		}
		if($loginHelper->userTfaConfirmationRequired($user)){
			$tfaModel = $this->getModelFromCache('XenForo_Model_Tfa');
			$providers = $tfaModel->getTfaConfigurationForUser($user['user_id'], $userData);
			if($providers){
				static::returnJson(['2fa','Two-factor authentication']);
			}
		}
		$this->finishLogin($userId);
	}

	public function finishLogin($userId){
		$userModel = $this->_getUserModel();
		$cookieKey = $userModel->getUserRememberKeyForCookie($userId, null);
		XenForo_Model_Ip::log($userId, 'user', $userId, 'login');
		$userModel->deleteSessionActivity(0, $this->_request->getClientIp(false));
		$visitor = XenForo_Visitor::setup($userId);
		XenForo_Application::getSession()->userLogin($userId, $visitor['password_date']);
		$sessionObj = XenForo_Application::getSession();
		$session_id = $sessionObj->getSessionId();
		$sessionObj->save();
		static::returnJson([true,json_encode([$userId,$session_id,$cookieKey])]);
	}

	public function actionLoginTfa(){
		$user = $this->actionLogin(true);
		$loginHelper = $this->getHelper('Login');
		$tfaModel = $this->getModelFromCache('XenForo_Model_Tfa');
		$providers = $tfaModel->getTfaConfigurationForUser($user['user_id'], $userData);
		if(!$providers){$this->finishLogin($user['user_id']);}
		$providerId = $this->_input->filterSingle('provider', XenForo_Input::STRING);
		if($providerId){
			$validationResult = $loginHelper->runTfaValidation($user, $providerId, $providers, $userData);
			if($validationResult===true){
				$this->finishLogin($user['user_id']);
			}
			static::returnJson([false,'Authentication has failed']);
		}
		$vp = [];
		foreach($providers as $id=>$pv){
			$vp[]=[
				'type'=>$id,
				'title'=>strval($pv->getTitle()),
				'desc'=>strval($pv->getDescription()),
			];
			$loginHelper->triggerTfaCheck($user, $id, $providers, $userData);
		}
		static::returnJson([
			false,
			strval(
				new XenForo_Phrase('email_has_been_sent_to_x_with_code',['email'=>$user['email']])
			),
			$vp
		]);
	}

	public function actionLogout(){
		$this->getModelFromCache('XenForo_Model_Session')->processLastActivityUpdateForLogOut(XenForo_Visitor::getUserId());
		XenForo_Application::getSession()->delete();
		XenForo_Helper_Cookie::deleteAllCookies(
			array('session'),
			array('user' => array('httpOnly' => false))
		);
		static::returnJson(true);
	}

	public function actionAlertList(){
		$visitor = XenForo_Visitor::getInstance();
		$alertModel = $this->_getAlertModel();
		$alerts = $alertModel->getAlertsForUser(
			$visitor->user_id,
			$alertModel::PREVENT_MARK_READ
		);
		return $this->responseView('mobileAppApi_ViewPublic_MobileApi','',[
			'mode'=>'alertCompiler',
			'alerts'=>$alerts
		]);
	}

	public function actionAlertMarkRead(){
		$visitor = XenForo_Visitor::getInstance();
		$alertModel = $this->_getAlertModel();
		$alertModel->markAllAlertsReadForUser($visitor->user_id);
		return $this->actionAlertList();
	}

	public function actionMyData(){
		$visitor = XenForo_Visitor::getInstance();
		static::returnJson($visitor->toArray());
	}

	public function actionMyDataMini(){
		$visitor = XenForo_Visitor::getInstance();
		$mini = [
			'user_id' => $visitor->user_id,
			'username' => $visitor->username,
			'custom_title' => $visitor->custom_title,
			'alerts_unread' => $visitor->alerts_unread,
			'conversations_unread' => $visitor->conversations_unread,
		];
		static::returnJson($mini);
	}

	public function actionListForum(){
		$visitor = XenForo_Visitor::getInstance();

		$fm = $this->_getForumModel();

		$fn = [];
		$fn2 = XenForo_Model::create('XenForo_Model_Node')->getAllNodes();
		$nodedatalist = XenForo_Model::create('XenForo_Model_Node')->getNodeDataForListDisplay(false,0);
		$fnref = array_keys($nodedatalist['nodeParents']);

		foreach($fnref as $nid){
			if(array_key_exists($nid,$fn2)){
				$fn[$nid] = $fn2[$nid];
			}
		}

		$forums = [];

		foreach($fn as $f){
			if($f['node_type_id']=='Forum' || $f['node_type_id']=='Category'){
				if(!$f['display_in_list']){
					continue;
				}
				$np = $visitor->getNodePermissions($f['node_id']);
				if(array_key_exists('view',$np) && $np['view']===true){
					if(!$f['display_in_list']){
						continue;
					}
					$forum = [
						'id'=>$f['node_id'],
						'nm'=>$f['title'],
						'dc'=>$f['description'],
						'dp'=>$f['depth'],
						'fr'=>$f['node_type_id']=='Forum',
						'ct'=>$f['node_type_id']=='Category',
						'nw'=>false,
					];
					if($forum['fr']){ //if is forum
						$ed = $this->_getForumModel()->getExtraForumDataForNodes(
							[$f['node_id']],
							['readUserId'=>$visitor->user_id]
						);
						$ed = $f+$ed[$f['node_id']];
						$ed = $this->_getForumModel()->prepareForum($ed);
						$forum['nw'] = $ed['hasNew'];
					}
					$forums[]=$forum;
				}
			}
		}
		for($i=0; $i<count($forums)-1; $i++){
			if($forums[$i]['ct'] && $forums[$i]['dp']>$forums[$i+1]['dp']){
				unset($forums[$i]);
				$forums = array_merge($forums);
				$i--;
			}
		}
		$forums = array_merge($forums);
		if($forums[count($forums)-1]['ct']){
			unset($forums[count($forums)-1]);
		}
		static::returnJson($forums);
	}
	//public function actionListForum(){return $this->responseView('mobileAppApi_ViewPublic_MobileApi','',['mode'=>'forumNodeCompiler','nodeList'=>$this->_getNodeModel()->getNodeDataForListDisplay(false, 0)]);}


	public function actionForumListThread(){
		$visitor = XenForo_Visitor::getInstance();
		$forumId = $this->_input->filterSingle('frm', XenForo_Input::UINT);
		$threadModel = $this->_getThreadModel();
		$forumModel = $this->_getForumModel();
		$forum = [];
		try{
			$forum = $this->getHelper('ForumThreadPost')->assertForumValidAndViewable(
				$forumId,
				[
					'readUserId' => $visitor->user_id,
					'watchUserId' => $visitor->user_id
				]
			);
		} catch(Exception $e){
			static::returnJson([]);
		}
		$page = max(1,$this->_input->filterSingle('pag', XenForo_Input::UINT));
		$threadsPerPage = 100;
		$order = $forum['default_sort_order'];
		$orderDirection = $forum['default_sort_direction'];
		$displayConditions = [];
		$prefixId = $this->_input->filterSingle('prfx', XenForo_Input::UINT);
		if ($prefixId){
			$displayConditions['prefix_id'] = $prefixId;
		}
		$threadFetchConditions = $displayConditions + $threadModel->getPermissionBasedThreadFetchConditions($forum);
		$threadFetchConditions['deleted']=false;
		$threadFetchConditions['moderated']=false;
		$threadFetchOptions = array(
			'join' => XenForo_Model_Thread::FETCH_USER,
			'readUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'postCountUserId' => $visitor['user_id'],
			'perPage' => $threadsPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);
		$totalThreads = $threadModel->countThreadsInForum($forumId, $threadFetchConditions);
		$threads = $threadModel->getThreadsInForum($forumId, $threadFetchConditions, $threadFetchOptions);
		$threadPrefixModel = $this->_getThreadPrefixModel();
		foreach($threads as &$thread){
			$prefixId = $thread['prefix_id'];
			$prefixPhrase = $threadPrefixModel->getPrefixTitlePhraseName($prefixId);
			$prefix = strval(new XenForo_Phrase($prefixPhrase));
			$thread['prefix_has'] = $prefix!=$prefixPhrase;
			$thread['prefix'] = $prefix==$prefixPhrase?'':$prefix;
			unset($thread);
		}
		$threads2 = [];
		$lessinfo = [
			'thread_id',
			'prefix_id',
			'prefix_has',
			'prefix',
			'title',
			'reply_count',
			'user_id',
			'username',
			'last_post_id',
			'last_post_user_id',
			'last_post_username',
			'last_post_date',
			'thread_read_date',
		];
		foreach($threads as $thread){
			$abridged = [];
			foreach($lessinfo as $info){
				$abridged[$info] = $thread[$info];
			}
			$abridged['has_unread'] = $abridged['last_post_date'] > $abridged['thread_read_date'];
			unset($abridged['last_post_date']);
			unset($abridged['thread_read_date']);
			$threads2[$thread['thread_id']] = $abridged;
		}
		static::returnJson([
			'page'=>$page,
			'pages'=>ceil($totalThreads/$threadsPerPage),
			'threads'=>$threads2,
		]);
	}

	public function actionForumThreadListPost(){
		$visitor = XenForo_Visitor::getInstance();
		$threadId = $this->_input->filterSingle('thd', XenForo_Input::UINT);
		$threadFetchOptions = array(
			'readUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'draftUserId' => $visitor['user_id'],
			'join' => XenForo_Model_Thread::FETCH_AVATAR
		);
		$forumFetchOptions = array(
			'readUserId' => $visitor['user_id']
		);
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);
		$threadModel = $this->_getThreadModel();
		$postModel = $this->_getPostModel();
		if($threadModel->isRedirect($thread)){
			static::returnJson(null);
		}
		$page = 1;
		$postsPerPage = 10000;
		$postFetchOptions = $this->_getPostModel()->getPermissionBasedPostFetchOptions($thread, $forum) + array(
			'join' => XenForo_Model_Post::FETCH_USER
			| XenForo_Model_Post::FETCH_USER_PROFILE
			| XenForo_Model_Post::FETCH_BBCODE_CACHE,
			'likeUserId' => XenForo_Visitor::getUserId()
		);
		$postFetchOptions += array(
			'perPage' => $postsPerPage,
			'page' => $page
		);
		$posts = $postModel->getPostsInThread($threadId, $postFetchOptions);
		$posts = $postModel->getAndMergeAttachmentsIntoPosts($posts);

		$maxPostDate = 0;
		$pagePosition = 0;
		$firstUnreadPostId = 0;
		$permissions = $visitor->getNodePermissions($thread['node_id']);
		foreach ($posts AS $id=>&$post){
			$post['position_on_page'] = ++$pagePosition;
			$post = $postModel->preparePost($post, $thread, $forum, $permissions);
			if ($post['post_date'] > $maxPostDate){
				$maxPostDate = $post['post_date'];
			}
			if ($post['isDeleted']){
				unset($posts[$id]);
				continue;
			}
			if ($post['isModerated']){
				unset($posts[$id]);
				continue;
			}
			if (!$firstUnreadPostId && $post['isNew']){
				$firstUnreadPostId = $post['post_id'];
			}
			unset($post);
		}
		$threadModel->markThreadRead($thread, $forum, $maxPostDate);
		$threadModel->logThreadView($threadId);
		return $this->responseView('mobileAppApi_ViewPublic_MobileApi', '', [
			'mode'=>'postCompiler',
			'posts'=>$posts,
			'firstUnreadPost'=>$firstUnreadPostId,
			'thread'=>$thread,
			'forum'=>$forum
		]);
	}

	public function actionListRecentThreadPosts(){
		$visitor = XenForo_Visitor::getInstance();
		$autoReadDate = XenForo_Application::$time - (XenForo_Application::getOptions()->readMarkingDataLifetime * 86400);
		$threadModel = $this->_getThreadModel();
		$threads = $threadModel->getThreads(
			[
				'deleted' => false,
				'moderated' => false,
				'find_new' => true,
				'not_discussion_type' => 'redirect',
				'last_post_date' => array('>', $autoReadDate)
			],[
				'limit' => 250,
				'join' =>
				XenForo_Model_Thread::FETCH_FORUM |
				XenForo_Model_Thread::FETCH_FORUM_OPTIONS |
				XenForo_Model_Thread::FETCH_LAST_POST_AVATAR,
				'permissionCombinationId' => $visitor->permission_combination_id,
				'readUserId' => $visitor->user_id,
				'order' => 'last_post_date',
				'forceThreadIndex' => 'last_post_date'
			]
		);
		$threadPrefixModel = $this->_getThreadPrefixModel();
		foreach($threads as &$thread){
			$np = $visitor->getNodePermissions($thread['node_id']);
			if(!(array_key_exists('view',$np) && $np['view']===true)){
				unset($threads[$thread['thread_id']]);
			}else{
				$prefixId = $thread['prefix_id'];
				$prefixPhrase = $threadPrefixModel->getPrefixTitlePhraseName($prefixId);
				$prefix = strval(new XenForo_Phrase($prefixPhrase));
				$thread['prefix_has'] = $prefix!=$prefixPhrase;
				$thread['prefix'] = $prefix==$prefixPhrase?'':$prefix;
			}
			unset($thread);
		}
		$threads2 = [];
		$lessinfo = [
			'node_id',
			'thread_id',
			'prefix_id',
			'prefix_has',
			'prefix',
			'title',
			'node_title',
			'reply_count',
			'username',
			'user_id',
			'last_post_id',
			'last_post_user_id',
			'last_post_username',
			'last_post_date',
			'thread_read_date',

		];
		foreach($threads as $thread){
			if($threadModel->isRedirect($thread)){
				continue;
			}
			$abridged = [];
			foreach($lessinfo as $info){
				$abridged[$info] = $thread[$info];
			}
			$abridged['isNew'] = (
				array_key_exists('thread_read_date',$abridged)
				&&
				array_key_exists('last_post_date',$abridged)
				&&
				$abridged['last_post_date'] > $abridged['thread_read_date']
			);
			$threads2[$thread['thread_id']] = $abridged;
		}
		static::returnJson($threads2);
	}

	public function actionForumInsertThread(){
		$visitor = XenForo_Visitor::getInstance();
		$testingPermissions = $this->_input->filterSingle('permtst', XenForo_Input::UINT);
		$forumId = $this->_input->filterSingle('frm', XenForo_Input::UINT);
		$ftpHelper = $this->getHelper('ForumThreadPost');
		try{
			$forum = $ftpHelper->assertForumValidAndViewable($forumId);
		}catch(Exception $e){
			static::returnJson([false,"Error","Forum is not valid or viewable"]);
		}
		$forumId = $forum['node_id'];
		if(!$this->_getForumModel()->canPostThreadInForum($forum, $errorPhraseKey)){
			try{
				static::returnJson([false,"Can't post into forum",strval(new XenForo_Phrase($errorPhraseKey))]);
			}catch(ErrorException $e){
				static::returnJson([false,"Can't reply into thread",""]);
			}
		}

		if($testingPermissions){
			static::returnJson([true,"Allowed","Can reply into thread"]);
		}

		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'prefix_id' => XenForo_Input::UINT,
			'attachment_hash' => XenForo_Input::STRING,
			'tags' => XenForo_Input::STRING,

			'watch_thread_state' => XenForo_Input::UINT,
			'watch_thread' => XenForo_Input::UINT,
			'watch_thread_email' => XenForo_Input::UINT,

			'_set' => array(XenForo_Input::UINT, 'array' => true),
			'discussion_open' => XenForo_Input::UINT,
			'sticky' => XenForo_Input::UINT,

			'poll' => XenForo_Input::ARRAY_SIMPLE,
		));
		$input['attachment_hash'] = '';
		$input['tags'] = '';
		$input['watch_thread_state'] = 1;
		$input['watch_thread'] = 1;
		$input['watch_thread_email'] = 1;
		$input['_set'] = [];
		$input['discussion_open'] = 1;
		$input['sticky'] = 0;
		$input['poll'] = [];

		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
		$writer->bulkSet(array(
			'user_id' => $visitor['user_id'],
			'username' => $visitor['username'],
			'title' => $input['title'],
			'prefix_id' => $input['prefix_id'],
			'node_id' => $forumId
		));
		$writer->set('discussion_state', $this->getModelFromCache('XenForo_Model_Post')->getPostInsertMessageState(array(), $forum));

		$postWriter = $writer->getFirstMessageDw();
		$postWriter->set('message', $input['message']);
		$postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $input['attachment_hash']);
		$postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
		$postWriter->setOption(XenForo_DataWriter_DiscussionMessage_Post::OPTION_MAX_TAGGED_USERS, $visitor->hasPermission('general', 'maxTaggedUsers'));

		$writer->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);
		$pollWriter = false;
		$tagger = null;

		$spamModel = $this->_getSpamPreventionModel();
		$spamMessage = '';

		if (!$writer->hasErrors()
		&& $writer->get('discussion_state') == 'visible'
		&& $spamModel->visitorRequiresSpamCheck()
		)
		{
			switch ($spamModel->checkMessageSpam($input['title'] . "\n" . $input['message'], array(), $this->_request)){
				case XenForo_Model_SpamPrevention::RESULT_MODERATED:
				//$writer->set('discussion_state', 'moderated');
				//let it pass
				break;

				case XenForo_Model_SpamPrevention::RESULT_DENIED;
				$spamModel->logSpamTrigger('thread', null);
				$spamMessage = new XenForo_Phrase('your_content_cannot_be_submitted_try_later');
				$writer->error($spamMessage);
				break;
			}
		}
		$writer->preSave();

		if ($forum['require_prefix'] &&
		!$writer->get('prefix_id') &&
		$this->_getPrefixModel()->getUsablePrefixesInForums($forum['node_id']))
		{
			$writer->set('prefix_id',$forum['default_prefix_id']);
		}

		if (!$writer->hasErrors()){
			$this->assertNotFlooding('post');
		}

		$writer->save();

		$thread = $writer->getMergedData();

		$spamModel->logContentSpamCheck('thread', $thread['thread_id']);
		$spamModel->logSpamTrigger('thread', $thread['thread_id']);

		$this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], $input);
		$this->_getThreadModel()->markThreadRead($thread, $forum, XenForo_Application::$time);

		static::returnJson([true,"Success",strval(new XenForo_Phrase('your_thread_has_been_posted'))]);
	}
	public function actionForumThreadInsertPost(){
		$visitor = XenForo_Visitor::getInstance();
		$testingPermissions = $this->_input->filterSingle('permtst', XenForo_Input::UINT);
		$threadId = $this->_input->filterSingle('thd', XenForo_Input::UINT);
		$ftpHelper = $this->getHelper('ForumThreadPost');
		$threadFetchOptions = array('readUserId' => $visitor['user_id']);
		$forumFetchOptions = array('readUserId' => $visitor['user_id']);
		try{
			list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);
		}catch(Exception $e){
			static::returnJson([false,"Error","Thread is not valid or viewable"]);
		}
		if (!$this->_getThreadModel()->canReplyToThread($thread, $forum, $errorPhraseKey)){
			try{
				static::returnJson([false,"Can't reply into thread",strval(new XenForo_Phrase($errorPhraseKey))]);
			}catch(ErrorException $e){
				static::returnJson([false,"Can't reply into thread",""]);
			}
		}

		if($testingPermissions){
			static::returnJson([true,"Allowed","Can reply into thread"]);
		}

		$input = $this->_input->filter(array(
			'attachment_hash' => XenForo_Input::STRING,

			'watch_thread_state' => XenForo_Input::UINT,
			'watch_thread' => XenForo_Input::UINT,
			'watch_thread_email' => XenForo_Input::UINT,

			'_set' => array(XenForo_Input::UINT, 'array' => true),
			'discussion_open' => XenForo_Input::UINT,
			'sticky' => XenForo_Input::UINT,
		));
		$input['attachment_hash'] = '';
		$input['watch_thread_state'] = 1;
		$input['watch_thread'] = 1;
		$input['watch_thread_email'] = 1;
		$input['_set'] = [];
		$input['discussion_open'] = 1;
		$input['sticky'] = 0;

		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
		$writer->set('user_id', $visitor['user_id']);
		$writer->set('username', $visitor['username']);
		$writer->set('message', $input['message']);
		$writer->set('message_state', $this->_getPostModel()->getPostInsertMessageState($thread, $forum));
		$writer->set('thread_id', $threadId);
		$writer->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $input['attachment_hash']);
		$writer->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
		$writer->setOption(XenForo_DataWriter_DiscussionMessage_Post::OPTION_MAX_TAGGED_USERS, $visitor->hasPermission('general', 'maxTaggedUsers'));

		$spamModel = $this->_getSpamPreventionModel();
		$spamMessage = '';

		if (!$writer->hasErrors()
		&& $writer->get('message_state') == 'visible'
		&& $spamModel->visitorRequiresSpamCheck()
		)
		{
			$spamExtraParams = array(
				'permalink' => XenForo_Link::buildPublicLink('canonical:threads', $thread)
			);
			switch ($spamModel->checkMessageSpam($input['message'], $spamExtraParams, $this->_request))
			{
				case XenForo_Model_SpamPrevention::RESULT_MODERATED:
				//$writer->set('message_state', 'moderated');
				//let it pass
				break;

				case XenForo_Model_SpamPrevention::RESULT_DENIED;
				$spamModel->logSpamTrigger('post', null);
				$spamMessage = new XenForo_Phrase('your_content_cannot_be_submitted_try_later');
				$writer->error($spamMessage);
				break;
			}
		}

		$writer->preSave();

		if (!$writer->hasErrors()){
			$this->assertNotFlooding('post');
		}

		$writer->save();
		$post = $writer->getMergedData();

		$spamModel->logContentSpamCheck('post', $post['post_id']);
		$spamModel->logSpamTrigger('post', $post['post_id']);

		$this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($threadId, $input);

		static::returnJson([true,"Success",strval(new XenForo_Phrase('your_message_has_been_posted'))]);
	}

	public function actionChatPermissions($asMethod = false){
		$visitor = XenForo_Visitor::getInstance();
		$chatEnabled = XenForo_Application::getOptions()->siropu_chat_page['enabled'];
		$chatPermissions = $visitor->permissions['siropu_chat'];
		$chatPermissions['chatBanned'] = boolval($this->_getChatModel()->getBanByUserAndRoomId($visitor->user_id, -1, 'ban'));
		$chatPermissions['chatKicked'] = $this->_getChatModel()->getBanByUserAndRoomId($visitor->user_id, -1, 'kick');
		$chatPermissions['chatKicked']|= $this->_getChatModel()->getBanByUserAndRoomId($visitor->user_id, 0, 'kick');
		$chatPermissions['chatKicked'] = boolval($chatPermissions['chatKicked']);
		$chatPermissions['chatMuted'] = $this->_getChatModel()->getBanByUserAndRoomId($visitor->user_id, -1, 'mute');
		$chatPermissions['chatMuted']|= $this->_getChatModel()->getBanByUserAndRoomId($visitor->user_id, 0, 'mute');
		$chatPermissions['chatMuted'] = boolval($chatPermissions['chatMuted']);
		$permissions = [];
		$permissions['see'] = $chatEnabled && $chatPermissions['view'] && (!($chatPermissions['chatBanned'] || $chatPermissions['chatKicked']));
		$permissions['say'] = $chatEnabled && $permissions['see'] && $chatPermissions['use'] && !$chatPermissions['chatMuted'];
		if($asMethod){
			return $permissions;
		}
		static::returnJson($permissions);
	}

	public function actionChatListRooms($asMethod = false){
		$visitor = XenForo_Visitor::getInstance();
		if(!$this->actionChatPermissions(true)['see']){
			$this->actionError();
		}
		$rooms = $this->_getChatModel()->getAllRooms();
		foreach($rooms as $id=>&$room){
			$room['room_name'] = strval($room['room_name']);
			$room['room_description'] = strval($room['room_description']);
			if(!array_key_exists('hasPermission',$room)){
				$room['hasPermission'] = $this->_getChatHelper()->checkRoomPermissions($room);
			}
			if(
				!$room['hasPermission']
				||
				(array_key_exists('room_password',$room)&&boolval($room['room_password']))
				||
				(array_key_exists('room_locked',$room)&&boolval($room['room_locked']))
				||
				boolval($this->_getChatModel()->getBanByUserAndRoomId($visitor->user_id,$room['room_id'],'ban'))
				||
				boolval($this->_getChatModel()->getBanByUserAndRoomId($visitor->user_id,$room['room_id'],'kick'))
			){
				unset($rooms[$id]);
			}
			$room['muted']=boolval($this->_getChatModel()->getBanByUserAndRoomId($visitor->user_id,$room['room_id'],'mute'));
			unset($room);
			unset($id);
		}
		ksort($rooms);
		$rooms2 = [];
		foreach($rooms as $id=>$room){
			$rooms2[$id]=[
				'room_id' => $room['room_id'],
				'room_name' => $room['room_name'],
				'room_description' => $room['room_description'],
				'room_speakable' => !$room['muted']
			];
		}
		if($asMethod){
			return $rooms2;
		}
		static::returnJson([array_keys($rooms2),array_values($rooms2)]);
	}

	public function actionChatRoomListMessages(){
		$visitor = XenForo_Visitor::getInstance();
		if(!$this->actionChatPermissions(true)['see']){
			$this->actionError();
		}
		$rooms = array_keys($this->actionChatListRooms(true));
		$lastMessage = $this->_input->filterSingle('messagelatest', XenForo_Input::UINT);
		$pollingTime = $this->_input->filterSingle('pollingtime', XenForo_Input::UINT);
		if($pollingTime<5){ //Won't overload the server with short pollings
			$pollingTime = 5;
		}else if($pollingTime>25){ //XenForo limits this the request time to 30
			$pollingTime = 25;
		}
		$msgs = [];
		for($i = 0; $i<($pollingTime*4); $i++){
			$gotData = false;
			foreach($rooms as $room){
				$ms = $this->_getChatModel()->getMessages([
					'order'=>'date_desc',
					'room_id'=>$room,
					'last_id'=>$lastMessage,
				]);
				$msgs[$room] = $ms;
				if(count($ms)>0){
					$gotData = true;
				}
			}
			if($gotData){
				return $this->responseView('mobileAppApi_ViewPublic_MobileApi','',[
					'mode'=>'chatMessageCompiler',
					'data'=>$msgs
				]);
			}else{
				usleep(1000*250);//250 ms
			}
		}
		static::returnJson([]);
	}

	public function actionChatRoomOnlineGet(){
		$visitor = XenForo_Visitor::getInstance();
		if(!$this->actionChatPermissions(true)['see']){
			$this->actionError();
		}
		$roomid = $this->_input->filterSingle('roomid', XenForo_Input::UINT);
		$active = $this->_getChatModel()->getActiveUsers();
		if(array_key_exists($roomid,$active)){
			static::returnJson($active[$roomid]['data']);
		}
		static::returnJson([]);
	}

	public function actionChatRoomMessageSend(){
		$visitor = XenForo_Visitor::getInstance();

		if(!$this->actionChatPermissions(true)['say']){
			static::returnJson([false,"Insufficient permissions"]);
		}
		$roomid = $this->_input->filterSingle('roomid', XenForo_Input::UINT);
		$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
		$rooms = $this->actionChatListRooms(true);
		if(array_key_exists($roomid,$rooms)){
			if($rooms[$roomid]['room_speakable']){
				if(!$message){
					static::returnJson([false,"Cannot send empty messages"]);
				}
				$chatSession = $this->_getChatModel()->getSession($visitor->user_id);
				if(!$chatSession){
					$dw = XenForo_DataWriter::create('Siropu_Chat_DataWriter_Sessions');
					$dw->setExistingData($this->userId);
					$dw->set('user_is_banned', 0);
					$dw->set('user_is_muted', 0);
					$dw->save();
					unset($dw);
				}
				$chatSession = $this->_getChatModel()->getSession($visitor->user_id);
				$csr = unserialize($chatSession['user_rooms']);
				if(getType($csr)!='array'){
					$csr = [];
				}
				$csr[$roomid]=time();
				$chatSession['user_rooms']=serialize($csr);
				$chatSession['user_last_activity']=time();
				$chatSession['user_message_count']+=1;
				$sw = XenForo_DataWriter::create('Siropu_Chat_DataWriter_Sessions');
				$sw->setExistingData($visitor->user_id);
				$sw->bulkSet($chatSession);
				$sw->save();
				//static::returnJson($chatSession);
				$pfx = XenForo_Application::getOptions()->chatMobilePrefix;
				$dw = XenForo_DataWriter::create('Siropu_Chat_DataWriter_Messages');
				$dw->set('message_room_id',$roomid);
				$dw->set('message_user_id',$visitor->user_id);
				$dw->set('message_text',$pfx?$pfx.' '.$message:$message);
				$dw->save();
				if($dw->hasErrors()){
					static::returnJson([false,"DataWriter failed"]);
				}
				static::returnJson([true,"Success"]);
			}
			static::returnJson([false,"Insufficient permissions"]);
		}
		static::returnJson([false,"Room doesn't exist"]);
	}
}
