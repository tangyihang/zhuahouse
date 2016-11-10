<?php
namespace common\modelsDB;

use Yii;
use yii\db\ActiveRecord;

/**
 * 小说采集详细规则
 * @author yihang
 *
 */
class CollectRuleDB extends ActiveRecord {
	
	public static function tableName() {
		return 'collect_rule';
	}
	
	/**
	 * 获取来源下规则的信息
	 * @param int $id
	 * @return \yii\caching\mixed
	 */
	public static function getRuleInfo($id){
		$key = 'CollectRuleDB_getRuleInfo_sourceid_'.$id;
		$rules = \Yii::$app->cache->get($key);
		if (empty($rules)) {
			$rules = self::find()->where(['sourceid'=>$id])->all();
			\Yii::$app->cache->set($key, $rules, ONE_MONTH_TIME);
		}
		return $rules;
	}
	
	/**
	 * //CollectRuleDB::setrule();//生成规则
	 * @return boolean
	 */
	public static function setrule(){
		$rule = new CollectRuleDB();
		$rule->sourceid = '1';//来源网站主键id
		$rule->typeid = '1';//采集规则类型1、房源列表页，2、房源详情页
		$rule->mcharset = 'utf-8';//字符集gbk，utf-8
		$rule->timeout = '30';//连接超时时间
		$rule->uregular = 'http://bj.lianjia.com/ershoufang/pg(*)co32/';//网址列表
		$rule->uregion = '<ul class="listContent" log-mod="list">';//列表区域标签
		$rule->uspilit = '</li>';//分隔标签
		$rule->ufromnum = '1';//列表开始页数
		$rule->utonum = '5';//列表结束页数
		$rule->updatetime = time();//更新时间
		$rule->save();
		var_dump($rule);
		return true;
	}
}