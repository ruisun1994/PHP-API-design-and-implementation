<?php
/**
 * 检查如果是微信的callback请求，转发去wxpay callbackAction
 *
 */

class IndexController extends Yaf_Controller_Abstract {
	public function indexAction() {
		$model = new WxpayModel();
		$model->callback();
        return TRUE;
	}
}
