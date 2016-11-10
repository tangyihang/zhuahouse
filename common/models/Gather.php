<?php
namespace common\models;

use Yii;
use common\helps\globals;
use common\modelsCLS\HttpCLS;
use common\modelsCLS\ChineseCLS;
use common\modelsCLS\CharsetFUN;
use common\modelsDB\BooksourceDB;
use common\modelsDB\CollectRuleDB;
use common\modelsDB\CollectFieldDB;
use common\modelsDB\InfobookDB;
use yii\helpers\VarDumper;
use common\modelsDB\MongoChapterDB;
use common\modelsDB\CollectSourceDB;
/**
 * 采集类
 * @author yihang
 *
 */
class Gather {
	
	public $sourceid = 0;
	public $sourcetype = 1;
	public $booklistRule = array();//书籍列表页规则
	public $booklistFields = array();//书籍列表页字段
	public $bookinfoRule = array();//书籍介绍页规则
	public $bookinfoFields = array();//书籍介绍页字段
	public $chapterListRule = array();//书籍章节列表页规则
	public $chapterListFields = array();//书籍章节列表页字段
	public $chapterRule = array();//书籍章节详情页规则
	public $chapterFields = array();//书籍章节详情页字段
	
	function __construct($sourceid) {
		$this->sourceid = $sourceid;
		$source = CollectSourceDB::getSourceInfo($sourceid);
		$rules = CollectRuleDB::getRuleInfo($sourceid);
		foreach ($rules as $rule) {
			$fields = CollectFieldDB::getFieldInfo($rule->ruleid);
			switch ($rule->typeid)
			{
				case 1: 
					$this->booklistRule = $rule;
					$this->booklistFields = $fields;
					//var_dump($this->booklistFields);
					break;
				case 2: 
					$this->bookinfoRule = $rule;
					$this->bookinfoFields = $fields;
					break;
				case 3: 
					$this->chapterListRule = $rule;
					$this->chapterListFields = $fields;
					break;
				case 4: 
					$this->chapterRule = $rule;
					$this->chapterFields = $fields;
					break;
			}
		}
	}
	
	/**
	 * 获取采集页面网址列表
	 */
	function fetch_surls() {
		$surls = array();
		if ($this->booklistRule && strpos( $this->booklistRule->uregular, '(*)') > 1) {
			for ($i = $this->booklistRule->ufromnum; $i <= $this->booklistRule->utonum; $i++) {
				$surls [] = str_replace ( "(*)", $i, $this->booklistRule->uregular );
			}
		}
		krsort ( $surls );//倒序采集
		return $surls;
	}
	
	
	/**
	 * 没有书籍介绍页的书籍信息采集
	 * 获取要采集的书籍的信息
	 * @param string $surl
	 */
	function fetch_info_books_gurls($surl){
		if (empty ( $surl ) || ! ($html = $this->onepage ( $surl ))) {
			die;return false; // 源网址不存在或无法读取该页面
		}
		//var_dump($this->booklistRule);die;
		//取出初始有效范围
		if (empty($this->booklistRule->uregion) || !($uhtml = $this->fetch_detail ( $this->booklistRule->uregion, $html ))) {
			die;return false;
		}
		$urlregions = explode ( $this->booklistRule->uspilit, $uhtml ); // 划出url区域
		var_dump($urlregions);die;
		foreach ( $urlregions as $urlregion ) { // 遍历每个url内容区块
			$this->clean_blank ( $urlregion );
			$contents = $this->fetch_fields($this->booklistFields, $urlregion, $surl);
			if (!empty($contents)) {
					
			} else {
				globals::setErrorLog(array('urlregion' => $urlregion, 'url' => $surl), 'nobooklist');
			}
		}
		var_dump($surl);
		unset($urlregions, $urlregion);
		return true;
	}
	
	/**
	 * 页面字段问题调试
	 */
	public function info_test(){
		$infohtml = $this->onepage ( 'http://www.ckxsw.com/chkbook/0/48687.html' );
		$infocontents = $this->fetch_fields($this->bookinfoFields, $infohtml, 'http://www.ckxsw.com/chkbook/0/48687.html');
		var_dump($infocontents);die;
		
		/* //测试更新获取数据
		$surl = 'http://www.baoliny.com/lastupdate_81.html';
		$gather = new Gather(3);
		$html = $gather->onepage ( $surl );
		var_dump($html);
		$uhtml = $gather->fetch_detail ( $gather->booklistRule->uregion, $html );
		var_dump($uhtml);
		$urlregions = explode ( $gather->booklistRule->uspilit, $uhtml );
		var_dump($urlregions);
		foreach ( $urlregions as $urlregion ) {
			$gather->clean_blank ( $urlregion );
			$contents = $gather->fetch_fields($gather->booklistFields, $urlregion, $surl);
			var_dump($contents);
		}
		die; */
	}
	
	/**
	 * 
	 * @param unknown $surl
	 * @return string|multitype:Ambigous <string, unknown>
	 */
	function chapter_field($surl){
		$contents = array();
		if (empty ( $surl ))
			return '';
		$html = $this->onepage ( $surl );
		if($html == '') return '';
		
		unset ( $html, $content, $field, $fname );
		return $contents;
	}
	
	/**
	 * 获取采集字段的值
	 * @param string $fname
	 * @param string $html
	 * @param string $reflink
	 */
	function fetch_fields($fields, $urlregion, $reflink){//当前任务，当前url情况下
		$contents = array();
		foreach ($fields as $field) {
			if (! $fieldvalue = $this->fetch_detail($field->uregular,$urlregion)) {
				if ($field->isitnull) {
					$contents[$field->fieldname] = $fieldvalue;
				} else {
					$contents = array();
					break;
				}
			} else {
				if ($field->type == 1) {
					$fieldvalue = $this->fillurl ( $fieldvalue, $reflink );//进行网址补全
				}
				if ($field->fieldname == '') {
					$this->clean_title($fieldvalue);
				}
				if (!empty($field->cleartext)) {
					$cleartexts = array_filter(explode ( '|', $field->cleartext ));
					if (!empty($cleartexts)) {
						foreach ($cleartexts as $value){
							$fieldvalue = preg_replace ( "/".$value."/i", "", $fieldvalue );
						}
					}
				}
				if (!empty($field->clearhtml)) {
					$this->clearhtml($field->clearhtml,$fieldvalue);//清除指定html信息
				}
				if (!empty($field->cleardefalt)) {
					$fieldvalue = str_replace ( $field->cleardefalt, "", $fieldvalue );//清除指定html信息
				}
				$contents[$field->fieldname] = trim($fieldvalue);
			}
		}
		unset($fieldvalue, $fields, $urlregion, $reflink);
		return $contents;
	}
	
	/**
	 * 根据规则字符串正则匹配出匹配的信息
	 * @param string $tagstr
	 * @param string $html
	 * @return string
	 */
	function fetch_detail($tagstring, $html) {
		if (! $tagstring)
			return '';
		$tagstrs = array_filter ( explode ( '##', $tagstring ) );
		$fetchstr = '';
		foreach ($tagstrs as $tagstr){
			$this->clean_blank ( $tagstr );
			$pos = strpos ( $tagstr, '(*)' );
			if (! $pos || $pos + 3 == strlen ( $pos ))
				return '';
			var_dump($tagstr);
			if (! preg_match ( '/' . $this->regencode ( $tagstr ) . '/is', $html, $matches ))
				continue;
			$fetchstr = &$matches [1];
			$this->clean_blank ( $fetchstr );
			unset ( $html, $tagstr, $matches );
			break;
		}
		
		return $fetchstr;
	}
	
	/**
	 * 采集页面
	 * @param string $url
	 */
	function onepage($url) {
		//如果未请求到数据，则重新请求，不超过5次
		for ($i=1;$i<=5;$i++) {
			$m_http = new HttpCLS ();
			if ($this->booklistRule->timeout)
				$m_http->timeout = $this->booklistRule->timeout;
			$html = $m_http->fetchtext ( $url );
			unset ( $m_http );
			$html = CharsetFUN::convert_encoding ( $this->booklistRule->mcharset, 'utf-8', $html );
			$this->clean_blank ( $html );
			if (!empty($html)) {
				break;
			} else {
				sleep(15);
			}
			globals::setErrorLog(array('url' => $url.'_'.$i), 'reload');
		}
		return $html;
	}
	
	/**
	 * 获取追溯网址
	 * @param string $surl
	 * @param string $pattern
	 * @param string $reflink
	 * @return string
	 */
	function fetch_addurl($surl, $pattern, $reflink) {
		if (empty ( $surl ) || empty ( $pattern ))
			return '';
		$html = $this->onepage ( $surl );
		$addurl = $this->fetch_detail ( $pattern, $html );
		$addurl = $this->fillurl ( $addurl, $reflink );
		unset ( $html );
		return $addurl;
	}
	
	/**
	 * 清理空格和回车字符
	 * @param string $str
	 */
	function clean_blank(&$str) {
		$str = preg_replace ( "/([\r\n|\r|\n]*)/is", "", $str );
		$str = preg_replace ( "/>([\s]*)</is", "><", $str );
		$str = preg_replace ( "/^([ ]*)/is", "", $str );
		$str = preg_replace ( "/([ ]*)$/is", "", $str );
	}
	
	/**
	 * 清理标题数据
	 */
	function clean_title(&$str){
		$str = preg_replace ( "/\d*\./i", "", $str );
		$str = preg_replace ( "/^正文/i", "", $str );
	}
	
	/**
	 * 对正则中需要转译的符号进行转译
	 * @param string $str
	 * @return string
	 */
	function regencode($str) {
		$search = array (
				"\\",
				'"',
				".",
				"[",
				"]",
				"(",
				")",
				"?",
				"+",
				"*",
				"^",
				"{",
				"}",
				"$",
				"|",
				"/",
				"\(\?\)",
				"\(\*\)"
		);
		$replace = array (
				"\\\\",
				'\"',
				"\.",
				"\[",
				"\]",
				"\(",
				"\)",
				"\?",
				"\+",
				"\*",
				"\^",
				"\{",
				"\}",
				"\$",
				"\|",
				"\/",
				".*?",
				"(.*?)"
		);
		return str_replace ( $search, $replace, $str );
	}
	
	/**
	 * 清除指定的html标签信息
	 * @param string $serial
	 */
	function clearhtml($serial, &$str) {
		if (! $serial || ! $str)
			return;
		$ids = array_filter ( explode ( ',', $serial ) );
		$search = array (
				"/<a[^>]*?>(.*?)<\/a>/is",//1
				"/<br[^>]*?>/i",//2
				"/<table[^>]*?>([\s\S]*?)<\/table>/i",//3
				"/<tr[^>]*?>([\s\S]*?)<\/tr>/i",//4
				"/<td[^>]*?>([\s\S]*?)<\/td>/i",//5
				"/<p[^>]*?>([\s\S]*?)<\/p>/i",//6
				"/<font[^>]*?>([\s\S]*?)<\/font>/i",//7
				"/<div[^>]*?>([\s\S]*?)<\/div>/i",//8
				"/<span[^>]*?>([\s\S]*?)<\/span>/i",//9
				"/<tbody[^>]*?>([\s\S]*?)<\/tbody>/i",//10
				"/<([\/]?)b>/i",//11
				"/<img[^>]*?>/i",//12
				"/[&nbsp;]{2,}/i",//13
				"/<script[^>]*?>([\w\W]*?)<\/script>/i",//14
				"/\d*\./i"//15去掉标题前的章节序号
		);
		$replace = array (
				"\\1",
				"",
				"\\1",
				"\\1",
				"\\1",
				"\\1",
				"\\1",
				"\\1",
				"\\1",
				"\\1",
				"",
				"",
				"&nbsp;",
				"\\1",
				"\\1"
		);
		foreach ( $ids as $id )
			$str = preg_replace ( $search [$id - 1], $replace [$id - 1], $str );
	}
	
	/**
	 * 对网址信息进行补全
	 * @param string $surl
	 * @param string $refhref
	 * @param string $basehref
	 * @return string
	 */
	function fillurl($surl, $refhref, $basehref = '') { // $refhref用以参照的完全网址
		$surl = trim ( $surl );
		$refhref = trim ( $refhref );
		$basehref = trim ( $basehref );
		if ($surl == '')
			return '';
	
		if ($basehref) {
			$preurl = strtolower ( substr ( $surl, 0, 6 ) );
			if (in_array ( $preurl, array (
					'http:/',
					'ftp://',
					'mms://',
					'rtsp:/',
					'thunde',
					'emule:',
					'ed2k:/'
			) )) {
				return $surl;
			} else {
				return $basehref . '/' . $surl;
			}
		}
	
		$urlparses = @parse_url ( $refhref );
		$homeurl = $urlparses ['host'];
		$baseurlpath = $homeurl . $urlparses ['path'];
		$baseurlpath = preg_replace ( "/\/([^\/]*)\.(.*)$/", "/", $baseurlpath );
		$baseurlpath = preg_replace ( "/\/$/", "", $baseurlpath );
	
		$i = $pathstep = 0;
		$dstr = $pstr = $okurl = '';
		$surl = (strpos ( $surl, "#" ) > 0) ? substr ( $surl, 0, strpos ( $surl, "#" ) ) : $surl;
		if ($surl [0] == "/") { // 不含http的绝对网址
			$okurl = "http://" . $homeurl . $surl;
		} elseif ($surl [0] == ".") { // 相对网址
			if (strlen ( $surl ) <= 1) {
				return "";
			} elseif ($surl [1] == "/") {
				$okurl = "http://" . $baseurlpath . "/" . substr ( $surl, 2, strlen ( $surl ) - 2 );
			} else {
				$urls = explode ( "/", $surl );
				foreach ( $urls as $u ) {
					if ($u == "..") {
						$pathstep ++;
					} elseif ($i < count ( $urls ) - 1) {
						$dstr .= $urls [$i] . "/";
					} else {
						$dstr .= $urls [$i];
					}
					$i ++;
				}
				$urls = explode ( "/", $baseurlpath );
				if (count ( $urls ) <= $pathstep) {
					return "http://" . $baseurlpath . '/' . $dstr;
				} else {
					$pstr = "http://";
					for($i = 0; $i < count ( $urls ) - $pathstep; $i ++) {
						$pstr .= $urls [$i] . "/";
					}
					$okurl = $pstr . $dstr;
				}
			}
		} else {
			$preurl = strtolower ( substr ( $surl, 0, 6 ) );
			if (strlen ( $surl ) < 7) {
				$okurl = "http://" . $baseurlpath . "/" . $surl;
			} elseif (in_array ( $preurl, array (
					'http:/',
					'ftp://',
					'mms://',
					'rtsp:/',
					'thunde',
					'emule:',
					'ed2k:/'
			) )) {
				$okurl = $surl;
			} else
				$okurl = "http://" . $baseurlpath . "/" . $surl;
		}
	
		$preurl = strtolower ( substr ( $okurl, 0, 6 ) );
		if (in_array ( $preurl, array (
				'ftp://',
				'mms://',
				'rtsp:/',
				'thunde',
				'emule:',
				'ed2k:/'
		) )) {
			return $okurl;
		} else {
			$okurl = preg_replace('/^(http:\/\/)/', "", $okurl);
			$okurl = preg_replace('/\/{1,}/', "/", $okurl);
			return "http://" . $okurl;
		}
	}
}