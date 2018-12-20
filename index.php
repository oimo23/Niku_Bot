<?php

// ****************************************
// APIKEY等の指定
// ****************************************
define(GNAVI_ACCESSKEY, "YOUR GNAVI API KEY");
define(LINE_TOKEN, "YOUR LINE TOKEN");
define(NO_IMAGE, "YOUR NOIMAGE URL");
define(KEYWORD, "肉");

// ****************************************
// ぐるなびAPIを叩いて結果をjsonでもらう
// ****************************************
function getGnavi($accessKey, $keyWord, $lat, $lng) {
	$uri  = 'https://api.gnavi.co.jp/RestSearchAPI/20150630/';
	$url  = $uri . '?format=json&name=' . $keyWord .'&range=5&keyid=' . $accessKey . '&latitude=' . $lat . '&longitude=' . $lng;
	$json = file_get_contents($url);
	$obj  = json_decode($json);

	return $obj;
}

// ****************************************
// 返信内容を作成する(店舗情報を配列に入れていく)
// ****************************************
function makeReply($obj, $limit) {
	$count = 0;
	$columns = array();

	foreach ($obj->rest as $restaurant) {
	  // 画像がない場合とある場合が混在しているとエラーになるので対処
	  $thumbnail = json_decode(json_encode($restaurant->image_url->shop_image1), true);
	  // 空画像の場合のみ配列形式となるので、その場合はNO_IMAGEを設定
	  if (is_array($thumbnail)) {
		$thumbnail = NO_IMAGE;
	  }

	  // 画像、テキスト、URLを配列に格納
	  $columns[] = array(
		'thumbnailImageUrl' => $thumbnail,
		'text'    => $restaurant->name,
		'actions' => array(array(
					  'type'  => 'uri',
					  'label' => '詳細を見る',
					  'uri'   => $restaurant->url
					))
	  );
	  // 制限数まで取得したら終了
	  $count++;
	  if ($count > $limit) {
		break;
	  }
	}

	// LINEで返信を行うための設定
	if ($columns) {
	  $template = array('type'    => 'carousel',
						'columns' => $columns);
	  $message = array('type'     => 'template',
					   'altText'  => KEYWORD,
					   'template' => $template);
	} else {
	  $message = array('type' => 'text',
					   'text' => '近くで' . KEYWORD . 'が見つかりませんでした。');
	}

	return $message;
}

// ****************************************
// curlでPOSTを実行し、返信する
// ****************************************
function doPOST($message, $accessToken) {
	$headers = array('Content-Type: application/json','Authorization: Bearer ' . $accessToken);
	
	$body = json_encode(array('replyToken' => $replyToken,
							  'messages'   => array($message)));
	
	$options = array(CURLOPT_URL            => 'https://api.line.me/v2/bot/message/reply',
					 CURLOPT_CUSTOMREQUEST  => 'POST',
					 CURLOPT_RETURNTRANSFER => true,
					 CURLOPT_HTTPHEADER     => $headers,
					 CURLOPT_POSTFIELDS     => $body);

	$curl = curl_init();
	curl_setopt_array($curl, $options);
	curl_exec($curl);
	curl_close($curl);	
}

function main() {
	// ****************************************
	// メッセージの受け取りとjsondecode
	// ****************************************
	$data = file_get_contents('php://input');
	$receive = json_decode($data, true);
	$event = $receive['events'][0];
	$replyToken  = $event['replyToken'];
	$messageType = $event['message']['type'];

	// ****************************************
	// メッセージが位置情報以外なら終了
	// ****************************************
	if($messageType != "location") exit;

	// ****************************************
	// 緯度経度の取得
	// ****************************************
	$lat = $event['message']['latitude'];
	$lng = $event['message']['longitude'];

	// ****************************************
	// ぐるなびAPIKEYとキーワードの指定
	// ****************************************
	$accessKey = GNAVI_ACCESSKEY;
	$keyWord = KEYWORD;

	// ****************************************
	// LINEのアクセストークン
	// ****************************************
	$accessToken = LINE_TOKEN;

	$obj = getGnavi($accessKey, $keyWord, $lat, $lng);
	$message = makeReply($obj, 5);
	doPOST($message, $accessToken);
}

main();

?>