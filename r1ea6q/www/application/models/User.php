<?php
/**
 * @name UserModel
 * @desc 用户操作Model类
 * @author pangee
 */
class UserModel {
	public $errno = 0;
	public $errmsg = "";
	private $_db;

    public function __construct() {
		$this->_db = new PDO("mysql:host=127.0.0.1;dbname=test;", "root", "123456");
    }   
    
	public function login( $uname, $pwd ) {
		$query = $this->_db->prepare("select `pwd`,`id` from `user` where `name`= ? ");
		$query->execute( array($uname) );
		$ret = $query->fetchAll();
		if ( !$ret || count($ret)!=1 ) {
			$this->errno = -1003;
			$this->errmsg = "用户查找失败";
			return false;
		}
		$userInfo = $ret[0];
		if( $this->_password_generate($pwd) != $userInfo['pwd'] ) {
			$this->errno = -1004;
			$this->errmsg = "密码错误";
			return false;
		}
		return intval($userInfo[1]);
	}

	public function register( $uname, $pwd ){
		$query = $this->_db->prepare("select count(*) as c from `user` where `name`= ? ");
		$query->execute( array($uname) );
		$count = $query->fetchAll();
		if( $count[0]['c']!=0 ) {
			$this->errno = -1005;
			$this->errmsg = "用户名已存在";
			return false;
		}

		if( strlen($pwd)<8 ) {
			$this->errno = -1006;
			$this->errmsg = "密码太短，请设置至少8位的密码";
			return false;
		} else {
			$password = $this->_password_generate( $pwd );
		}

		$query = $this->_db->prepare("insert into `user` (`id`, `name`,`pwd`,`reg_time`) VALUES ( null, ?, ?, ? )");
		$ret = $query->execute( array($uname, $password, date("Y-m-d H:i:s")) );
		if( !$ret ) {
			$this->errno = -1006;
			$this->errmsg = "注册失败，写入数据失败";
			return false;
		}
		return true;
	}

	private function _password_generate( $password ){
		$pwd = md5( "salt-xxxxxxxxx-".$password );
		return $pwd;
	}

}
