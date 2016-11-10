<?php
namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use common\models\Gather;
use common\modelsDB\CollectFieldDB;
use common\modelsDB\CollectRuleDB;
/**
 * Site controller
 */
class SiteController extends Controller {
   
    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionIndex()
    {
    	//CollectRuleDB::setrule();//生成规则
    	//die;
    	$gather = new Gather(1);
		$surls = $gather->fetch_surls();//获取采集地址列表
		//var_dump($surls);die;
		foreach($surls as $surl) $gather->fetch_info_books_gurls($surl);//进行书籍采集
		return true;
    }

}
