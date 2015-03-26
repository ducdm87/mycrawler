<?php
/**
 * Translator default controller hhh
 *
 * @package		noozsunnote
 * @subpackage	Components
 * @link		http://noozsunnote.com
 * @author		Đàm mạnh Đức
 * @copyright 	Đàm mạnh Đức (ducdm87@gmail.com)
 * @license		Commercial
 */

jimport('joomla.application.component.controller');
require_once( JPATH_COMPONENT_ADMINISTRATOR.'/helpers/tidy_clean.php' );
require_once( JPATH_COMPONENT_ADMINISTRATOR.'/helpers/href.php' );
/**
 * Translator Component Controller
 *
 * @package		noozsunnote
 */
class JPController extends JPDController
{

	// http://noozsunnote.com/index.php?option=com_noozsunnote&task=cron&sourceID=1&jobID=1
	function cron(){
		global $mainframe;	
		
		$filelog = dirname(__FILE__)."/file-log-cron.txt";
		$fp = fopen($filelog,'a+');
		fputs($fp,"------------------------------- \r\n");
		fputs($fp,date("Y-m-d H:i:s") . " runing \r\n");
		fclose($fp);
		echo "<br /> Start: ".date("Y-m-d H:i:s");
		
		$defaultTime = ini_get('max_execution_time');
		set_time_limit(1800);
		
		$model = $this->getModel('Cron');
		$arr_job = $model->getJob(1);
		if(count($arr_job)){
			echo "<br /> Process: ".count($arr_job) . " job ";
			foreach($arr_job as $job){
				echo "<hr /> Running $job->title";
				$arr_article = $model->runJob($job);
				$this->saveArticle($arr_article);
				echo "<br /> Result ". count($arr_article);
			}
		}else{
			echo "No job";
		}
		set_time_limit($defaultTime);
		
		$fp = fopen($filelog,'a+');
		fputs($fp,date("Y-m-d H:i:s") . " End \r\n");
		fclose($fp);
		echo "<br /> End: ".date("Y-m-d H:i:s");
		die;
	}
	
	// status: -1: moi crawl ve
	// 		    0: dang lay anh
	// 		   -2: da lay anh xong. co the chuyen sang convert
	//		    1: da convert xong
	function saveArticle($items)
	{
		global $db;
		$href	=	new href();
		$model = $this->getModel('Cron');
		foreach ($items as $item) {
			// lay cac field cua object nay
			$fields = $model->getObjectFields($item->objectID);
			// xu ly du lieu 
			$arr_meta = array();
			foreach ($fields as $fieldID => $field) {
				if($field->state != 1) continue;
				if(!isset($item->datamain[$fieldID]) AND !isset($item->datadetail[$fieldID])){
					if($field->required == 1){
						$message = $field->name . " is required.";
						$model->logErrorRuler($item->rulerID, $item->link_detail, $message, $field->id, 1);
					}
					continue;
				}
				$value = "";
				// uu tien lay du lieu trang chinh
				 if($field->premiummainpage == 1){
				 	$bool = isset($item->datamain[$fieldID]) AND $item->datamain[$fieldID] != null AND $item->datamain[$fieldID] != false;
				 	$value = $bool?$item->datamain[$fieldID]:$item->datadetail[$fieldID];
				 }else{ // uu tien lay du lieu trang chi tiet
				 	$bool = isset($item->datadetail[$fieldID]) AND $item->datadetail[$fieldID] != null AND $item->datadetail[$fieldID] != false;
				 	$value = $bool?$item->datadetail[$fieldID]:$item->datamain[$fieldID];
				 }
				 
				 // xu ly du lieu
				 $value = $model->processData($field->datatype, $value, $item->link_detail);
				 
				 $strlen = strlen($value);
				 if(intval($field->minlength)>0 AND $strlen < intval($field->minlength)){
				 	$message = $field->name . " is required minlength: $field->minlength.[$strlen - $value]";
				 	$model->logErrorRuler($item->rulerID, $item->link_detail, $message, $field->id, 1);
				 	continue;
				 }
				 
				if(intval($field->maxlength) >0 AND $strlen > intval($field->maxlength)){
				 	$message = $field->name . " is required maxlength: $field->maxlength.[$strlen - $value]";
				 	$model->logErrorRuler($item->rulerID, $item->link_detail, $message, $field->id, 1);
				 	continue;
				 }
				if($field->required == 1 AND ($value == null OR $value == false) ){
					$message = $field->name . " is required.";
					$model->logErrorRuler($item->rulerID, $item->link_detail, $message, $field->id, 1);
					continue;
				}
				
				if($value == null OR $value == false) $value = "";
				 
				 $arr_meta[$fieldID] = $value;
			}
			
			// luu bai viet
			$alias = $href->take_file_name($item->title);
			$query = "INSERT INTO `#__jp_received`
						SET `rulerID` = $item->rulerID".
							",`language` = ". $db->quote($item->language).
							",`link` = ". $db->quote($item->link_detail).
							",`title` = ". $db->quote($item->title).
							",`alias` = ".$db->quote($alias) .
							",`metaKey` = ". $db->quote($item->metaKey).
							",`metaDesc` = ". $db->quote($item->metaDesc).
							",`objectID` = $item->objectID".
							",`sourceID` = $item->sourceID".
							",`catID` = $item->catID".
							",`cdate` = now()".
							",`mdate` = now()".
							",`status` = -1".
						" ON DUPLICATE KEY UPDATE `mdate` = now() ". 
							",`title` = ". $db->quote($item->title).
							",`language` = ". $db->quote($item->language).
							",`alias` = ". $db->quote($alias).
							",`metaKey` = ". $db->quote($item->metaKey).
							",`metaDesc` = ". $db->quote($item->metaDesc).
							",`objectID` = $item->objectID".
							",`sourceID` = $item->sourceID".
							",`catID` = $item->catID ";
			$db->setQuery($query);
			$db->query();
			$cid = $db->insertid();
			// luu chi tiet
			$this->insertMetaData($arr_meta, $cid);
		}
		
	}
	
	function insertMetaData($arr, $cid){
		global $db;
		foreach ($arr as $fieldID=>$value) {
			$query = "INSERT INTO `#__jp_received_detail`
						SET `recivedID` = $cid".
							",`fieldID` = ". $db->quote($fieldID).
							",`value` = ". $db->quote($value).
							",`cdate` = now()".
							",`mdate` = now()".
							",`status` = -1".
						" ON DUPLICATE KEY UPDATE `mdate` = now() ". 
							",`value` = ". $db->quote($value);
			$db->setQuery($query);
			$db->query();
		}
	}
	
	//  http://noozsunnote.com/index.php?option=com_noozsunnote&task=cronImage
	function cronImage(){
		global $mainframe;
		require_once( JPATH_COMPONENT_ADMINISTRATOR.'/helpers/images.php' );
		require_once( JPATH_COMPONENT_ADMINISTRATOR.'/helpers/get_image.php' );
		require_once( JPATH_COMPONENT_ADMINISTRATOR.'/helpers/phpWebHacks.php' );
		$defaultTime = ini_get('max_execution_time');
		set_time_limit(900);
		echo "<br /> Start: ".date("Y-m-d H:i:s");
		$start = microtime(true);
		$model = $this->getModel('Cron');
		$number = $model->cronImage();
		set_time_limit($defaultTime);
		echo "<br /> End: ".date("Y-m-d H:i:s");
		$end = microtime(true);
		$length = $end - $start;
		echo "<br /> Sucsessfull cron image for $number items. $length s ";
		die;
	}
	
	// http://noozsunnote.com/index.php?option=com_noozsunnote&task=convertSunArticle
	function convertSunArticle()
	{
		global $db;
		$start = microtime(true);
		echo "<br /> Start: ".date("Y-m-d H:i:s");
		$objectID = 1;
		$query = "SELECT * FROM #__jp_received 
					WHERE `objectID` = $objectID 
							AND `status` < -1 AND `status` >-5  
							LIMIT 0,100 ";
		$db->setQuery($query);
		$items = $db->loadObjectList("id");
		if(count($items) == 0 ) die(" <br /> Het");
 
		$items_id = array_keys($items);
		$items_id = implode(",",$items_id);
		$query = "UPDATE #__jp_received SET status = -4 WHERE `id` IN($items_id)";
		$db->setQuery($query);
		$db->query();
		
		$query = "SELECT * FROM #__jp_received_detail WHERE `recivedID` IN($items_id)";
		$db->setQuery($query);
		$items_meta = $db->loadObjectList();
		$items_meta_new = array();
		foreach ($items_meta as $item_meta) {
			 $items_meta_new[$item_meta->recivedID][] = $item_meta;
		}
		// can bo sung che do auto cuttext neu introtext rong
		$arr_field = array(1=>"introtext",5=>"fulltext",3=>"created",2=>"thumbs",4=>"author");
		foreach ($items as $item) {
			$item->introtext = "";
			$item->fulltext = "";
			$item->created = "";
			$item->thumbs = "";
			$item->author = "";
			$item_meta = $items_meta_new[$item->id];
			$datastatus = true;
			foreach ($item_meta as $meta) {
				 if(!isset($arr_field[$meta->fieldID])) die("loi truong du lieu");
				 $fieldName = $arr_field[$meta->fieldID];
				 $item->$fieldName = $meta->value;
				 if($meta->status == -1) $datastatus = false;
			}
			if($datastatus == false) continue; // bai chua crawl xong

			 if($item->introtext == ""){
			 //	echo $item->id; echo '<hr />';
			 }
			 if($item->thumbs == ""){
				$query = "SELECT `url` FROM #__jp_received_media WHERE  recivedID = $item->id ORDER BY `cdate` ASC ";
				$db->setQuery($query);
				if($thumbs = $db->loadResult()){
					$item->thumbs = $thumbs;
				}
			 }
			$desc = $item->metaDesc!=""?$item->metaDesc:$item->introtext;
			$data_search = "$item->fulltext $desc $desc $item->title $item->title $item->title";
			$data_search .= "$item->metaKey $item->metaKey $item->metaKey $item->metaKey";
			$query = "INSERT INTO #__sunnote_article ".
					" SET recivedID = $item->id ".
						" ,`sourceID` = ". $db->quote($item->sourceID).
						" ,`language` = ". $db->quote($item->language).
						" ,`title` = ". $db->quote($item->title).
						" ,`alias` = ". $db->quote($item->alias).
						" ,`thumbs` = ". $db->quote($item->thumbs).
						" ,`introtext` = ". $db->quote($item->introtext).
						" ,`fulltext` = ". $db->quote($item->fulltext).
						" ,`author` = ". $db->quote($item->author).
						" ,`data_search` = ". $db->quote($data_search).
						" ,`catID` = $item->catID".
						" ,`cdate` = now()".
						" ,`mdate` = now()".
						" ,`created` = ". $db->quote($item->created).
						" ,`metaKey` = ". $db->quote($item->metaKey).
						" ,`metaDesc` = ". $db->quote($item->metaDesc).
						" ,`sourlUrl` = ". $db->quote($item->link).
						" ,`status` = 1 ".
					" ON DUPLICATE KEY UPDATE `mdate` = now()  ".
						" ,`sourceID` = ". $db->quote($item->sourceID).
						" ,`language` = ". $db->quote($item->language).
						" ,`title` = ". $db->quote($item->title).
						" ,`alias` = ". $db->quote($item->alias).
						" ,`thumbs` = ". $db->quote($item->thumbs).
						" ,`introtext` = ". $db->quote($item->introtext).
						" ,`fulltext` = ". $db->quote($item->fulltext).
						" ,`catID` = $item->catID".
						" ,`author` = ". $db->quote($item->author).
						" ,`data_search` = ". $db->quote($data_search).
						" ,`created` = ". $db->quote($item->created).
						" ,`sourlUrl` = ". $db->quote($item->link).
						" ,`metaKey` = ". $db->quote($item->metaKey).
						" ,`metaDesc` = ". $db->quote($item->metaDesc);
			$db->setQuery($query);
			$db->query();
			
			$query = "UPDATE #__jp_received SET status = 1 WHERE `id` = $item->id ";
			$db->setQuery($query);
			$db->query();
			
			$arr_keyword = explode(",", $item->metaKey);
			foreach ($arr_keyword as $keyword) {
				$keyword = trim(strip_tags($keyword)," ,-\"'");
				if($keyword == "") continue;
				$key_search = convertSearchUtf8($keyword);
			 	$query = "INSERT INTO #__sunnote_tag ".
					" SET status = 1 ".
						" ,`tag` = ". $db->quote($keyword).
						" ,`search` = ". $db->quote($key_search).
						" ,`cdate` = now()".
						" ,`mdate` = now()".
					" ON DUPLICATE KEY UPDATE `mdate` = now() ";
				$db->setQuery($query);
				$db->query();
			 }
		}
		$end = microtime(true);
		$length = $end - $start;
		echo "<br /> End: ".date("Y-m-d H:i:s");
		echo "<br /> Sucsessfull convert ".count($items)." items. ";
		echo "$length s ";
		die;
	}
	
	/**
	 * xoa bai viet cu 
	 * xoa bai viet trong __jp_received qua 60 ngay
	 * xoa bai viet trong __jp_demo_news qua 30 ngay
	 * xoa ca anh lien quan cua bai trong __jp_demo_news vua bi xoa
	 *  http://noozsunnote.com/index.php?option=com_noozsunnote&task=removeContent
	 */
	
	function removeContent(){
		global $db;
		$max1 = 360;
		$max2 = 30;
		
		$start = microtime(true);
		
		$query = "DELETE FROM #__jp_received WHERE `cdate` < DATE_SUB(now(),INTERVAL $max1 DAY)";
		$db->setQuery($query);
		$db->query();
		
		$query = "DELETE FROM #__jp_received_detail WHERE `cdate` < DATE_SUB(now(),INTERVAL $max1 DAY)";
		$db->setQuery($query);
		$db->query();
			
		/*$query = "DELETE FROM #__sunnote_article WHERE `cdate` < DATE_SUB(now(),INTERVAL $max2 DAY)";
		$db->setQuery($query);
		$db->query();
			
		$query = "SELECT * FROM #__jp_received_media WHERE `cdate` < DATE_SUB(now(),INTERVAL $max2 DAY) ";
		$db->setQuery($query);
		$list_images = $db->loadObjectList();
		
		$query = "DELETE FROM #__jp_received_media WHERE `cdate` < DATE_SUB(now(),INTERVAL $max2 DAY)";
		$db->setQuery($query);
		$db->query();
		
		foreach ($list_images as $image) {
			unlink($image->path."/".$image->fileName);
		}
		*/
		$end = microtime(true);
		$length = $end - $start;
		echo "Sucsessfull remove ".count($list_images)." images. ";
		echo "$length s ";
		die;
	}
	
	function processTmp()
	{
		global $db;
		echo "<br /> Start: ".date("Y-m-d H:i:s");
		$max1 = 360;
		// set client get audio
		$timecheck = date("Y-m-d H:i:s", time() - 3600);
		$query = "DELETE FROM `#__sunnote_article_getaudio` WHERE `timeout` < DATE_SUB(now(),INTERVAL 1 HOUR) ";
		$db->setQuery($query);
		$db->query();
		
		$timecheck = date("Y-m-d H:i:s", time() - 365 * 24 * 3600);
		$query = "DELETE FROM `#__jp_received` WHERE `cdate` < DATE_SUB(now(),INTERVAL $max1 DAY)  ";
		$db->setQuery($query);
		$db->query();
		
		$query = "DELETE FROM #__jp_received_detail WHERE `cdate` < DATE_SUB(now(),INTERVAL $max1 DAY)";
		$db->setQuery($query);
		$db->query();
		
		echo "<br /> End: ".date("Y-m-d H:i:s");
		die;
	}
	
}
?>
