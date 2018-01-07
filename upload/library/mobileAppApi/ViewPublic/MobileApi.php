<?php

class mobileAppApi_ViewPublic_MobileApi extends XenForo_ViewPublic_Base {
    public function renderHtml(){
        if($this->_params['mode']==='alertCompiler'){
            $this->_params['alerts'] = XenForo_ViewPublic_Helper_Alert::getTemplates(
                $this,
                $this->_params['alerts']['alerts'],
                $this->_params['alerts']['alertHandlers']
            );
            foreach($this->_params['alerts'] as &$alert){
                $alert['rendered'] = $alert['template']->render();
                unset($alert);
            }
            mobileAppApi_ControllerPublic_MobileApi::returnJson($this->_params['alerts']);
        }
        if($this->_params['mode']==='postCompiler'){
            $bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
            $bbCodeOptions = array(
                'states' => array(
                    'viewAttachments' => false
                )
            );
            XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['posts'], $bbCodeParser, $bbCodeOptions);
            foreach($this->_params['posts'] as &$post){
                if(array_key_exists('signatureHtml',$post)){
                    $post['signatureHtml']=strval($post['signatureHtml']);
                }else{
                    $post['signatureHtml']='';
                }
                if(array_key_exists('messageHtml',$post)){
                    $post['messageHtml']=strval($post['messageHtml']);
                }else{
                    $post['messageHtml']='';
                }
                unset($post);
            }
            $posts = $this->_params['posts'];
            $posts2 = [];
            $lessinfo = [
                'post_id',
                'thread_id',
                'user_id',
                'username',
                'isNew',
                'post_date',
                'message',
                'messageHtml',
                'signatureHtml',
            ];
            foreach($posts as $post){
                $abridged = [];
                foreach($lessinfo as $info){
                    if(array_key_exists($info,$post)){
                        $abridged[$info] = $post[$info];
                    }else{
                        $abridged[$info] = null;
                    }
                }
                $posts2[$post['post_id']] = $abridged;
            }
            $this->_params['posts'] = $posts2;
            $threads = [$this->_params['thread']];
            $threads2 = [];
            $lessinfo = [
                'node_id',
                'thread_id',
                'title',
                'reply_count',
                'last_post_id',
                'last_post_user_id',
                'last_post_username',
            ];
            foreach($threads as $thread){
                $abridged = [];
                foreach($lessinfo as $info){
                    $abridged[$info] = $thread[$info];
                }
                $threads2[] = $abridged;
            }
            $this->_params['thread']=$threads2[0];
            $this->_params['forum'] = [
                'id'=>$this->_params['forum']['node_id'],
                'nm'=>$this->_params['forum']['title'],
                'dc'=>$this->_params['forum']['description'],
            ];
            mobileAppApi_ControllerPublic_MobileApi::returnJson([
                'forum'=>$this->_params['forum'],
                'thread'=>$this->_params['thread'],
                'firstUnreadPost'=>$this->_params['firstUnreadPost'],
                'posts'=>$this->_params['posts'],
            ]);
        }
        if($this->_params['mode']==='chatMessageCompiler'){
            $data = $this->_params['data'];
            $bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
            foreach($data as &$messageList){
                foreach($messageList as &$message){
                    $message['message_compiled'] = $bbCodeParser->render($message['message_text']);
                    $message['banner'] = ($message['is_admin']?'admin':($message['is_moderator']?'moderator':(($message['is_staff']?'staff':''))));
                    unset($message);
                }
                unset($messageList);
            }
            mobileAppApi_ControllerPublic_MobileApi::returnJson($data);
        }
    }
}
