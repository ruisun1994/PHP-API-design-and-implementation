<?php
/**
 * @name MailModel
 * @desc 邮件操作Model类
 * @author pangee
 */
require __DIR__ . '/../../vendor/autoload.php';
use Nette\Mail\Message;

class MailModel {
	public $errno = 0;
	public $errmsg = "";
	private $_db;

    public function __construct() {
		$this->_db = new PDO("mysql:host=127.0.0.1;dbname=test;", "root", "123456");
    }   
    
	public function send( $uid, $title, $contents ) {
		$query = $this->_db->prepare("select `email` from `user` where `id`= ? ");
		$query->execute( array(intval($uid)) );
		$ret = $query->fetchAll();
		if ( !$ret || count($ret)!=1 ) {
			$this->errno = -3003;
			$this->errmsg = "用户邮箱信息查找失败";
			return false;
		}
		$userEmail = $ret[0]['email'];
		if( !filter_var($userEmail, FILTER_VALIDATE_EMAIL) ) {
			$this->errno = -3004;
			$this->errmsg = "用户邮箱信息不符合标准，邮箱地址为：".$userEmail;
			return false;
		}

		$mail = new Message;
		$mail->setFrom('PHP实战课程-高价值的PHP API <imooc_phpapi@126.com>')
			->addTo( $userEmail )
			->setSubject( $title )
			->setBody( $contents );
		
		$mailer = new Nette\Mail\SmtpMailer([
				'host' => 'smtp.126.com',
				'username' => 'imooc_phpapi@126.com',
				'password' => 'phpapi321', /* smtp独立密码 */
				'secure' => 'ssl',
		]);
		$rep = $mailer->send($mail);
		return true;
	}

}
