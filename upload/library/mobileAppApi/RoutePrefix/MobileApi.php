<?php

class mobileAppApi_RoutePrefix_MobileApi implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		if($routePath === ''){
			$routePath = 'index';
		}
		if(!method_exists('mobileAppApi_ControllerPublic_MobileApi','action'.$routePath)){
			$routePath = 'error';
		}
		return $router->getRouteMatch('mobileAppApi_ControllerPublic_MobileApi', $routePath, '');
	}
}
