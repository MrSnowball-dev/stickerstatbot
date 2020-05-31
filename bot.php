<?php
ini_set('display_errors', 1);
include 'config.php';
header("Content: text/html; encoding: utf-8");

$api = 'https://api.telegram.org/bot'.$tg_bot_token;

$input = file_get_contents('php://input');
$output = json_decode($input, TRUE); //сюда приходят все запросы по вебхукам

//телеграмные события
$chat_id = isset($output['message']['chat']['id']) ? $output['message']['chat']['id'] : 'chat_id_empty'; //отделяем id чата, откуда идет обращение к боту
$chat = isset($output['message']['chat']['title']) ? $output['message']['chat']['title'] : 'chat_title_empty';
$message = isset($output['message']['text']) ? $output['message']['text'] : 'message_text_empty'; //сам текст сообщения
$user = isset($output['message']['from']['username']) ? $output['message']['from']['username'] : 'origin_user_empty';
$user_id = isset($output['message']['from']['id']) ? $output['message']['from']['id'] : 'origin_user_id_empty';
$message_id = isset($output['message']['message_id']) ? $output['message']['message_id'] : 'message_id_empty';
$forward = isset($output['message']['forward_from']) ? $output['message']['forward_from'] : 'forward_empty';
$from_stickerbot = isset($output['message']['forward_from']['username']) ? $output['message']['forward_from']['username'] : 'from_stickerbot_empty';

$dig_date = [];
$dig_installed = [];
$dig_usage = [];
$dig_removed = [];

echo "Init successful.\n";

//----------------------------------------------------------------------------------------------------------------------------------//

if ($message == '/start') {
	sendMessage($chat_id, "Hi\! To use me, forward all stat messages from @Stickers to me\.");
}

//date,usage,installed,deleted

if ($forward !== 'forward_empty') {
	if ($from_stickerbot == 'Stickers') {
		$filename = 'stat_'.$chat_id.'.csv';
		if (!file_exists($filename)) {
			$statfile = fopen($filename, 'w');	
			$csv_header = ['Date', 'Usage', 'Installed', 'Removed'];
			fputcsv($statfile, $csv_header);
		} else {
			$statfile = fopen($filename, 'a');	
		}
		$digested_forward = strtok($message, "\r\n");
		$counter = 1;
		while ($counter <= 4) {
			switch ($counter) {
				//date
				case 1:
					$dig_date = preg_split('/\ (?!.*\ .*$)/', $digested_forward);
					$dig_date[1] = rtrim($dig_date[1], ':');
					$exploded_date = explode('/', $dig_date[1]);
					if (count($exploded_date) == 3) {
						$swap_temp = $exploded_date[1];
						$exploded_date[1] = $exploded_date[0];
						$exploded_date[0] = $swap_temp;
					}
					$dig_date[1] = implode('.', $exploded_date);
					$digested_forward = strtok("\r\n");
					break;
				
				//usage
				case 2:
					$dig_usage = explode(': ', $digested_forward);
					$digested_forward = strtok("\r\n");
					break;
	
				//installed
				case 3:
					$dig_installed = explode(': ', $digested_forward);
					$digested_forward = strtok("\r\n");
					break;
	
				//removed
				case 4:
					$dig_removed = explode(': ', $digested_forward);
					$digested_forward = strtok("\r\n");
					break;
			}
			$counter++;
		}
		
		$to_csv = [$dig_date[1], $dig_usage[1], $dig_installed[1], $dig_removed[1]];
		fputcsv($statfile, $to_csv);
		fclose($statfile);
	} else {
		sendMessage($chat_id, 'This bot only accepts forwards from @Stickers\.');
	}
}

if ($message == '/done') {
	if (file_exists('stat_'.$chat_id.'.csv')) {
		sendFile($chat_id);
	} else {
		sendMessage($chat_id, 'You have not generated your CSV\. Try to forward stat messages from @stickers first, or contact @mrsnowball if you beleive something is not right\.');
	}
}

if ($message == '/help') {
	sendMessage($chat_id, "This is a simple parser of your sticker stats\n\nThe workflow is simple \(only pack stats supported, not individual sticker stats\)\:\n1\. You get stats for each day, or month, or year from @Stickers for your pack, he sends you messages with the stats\n2\. After that, you forward all the messages with stats to this bot \(the limit is 100 messages at once\)\n3\. The bot will parse all your messages into one CSV file, wait a couple of seconds\n_3a\. \(optional\) Send the next batch of stat messages, bot will process them and append to the end of your CSV_\n4\. Type /done to get your CSV\n5\. Type /clear to delete your CSV file from server\n\nThe header for the file is _*Date,Usage,Installed,Removed*_, date is formatted as DD\.MM\.YYYY");
}

if ($message == '/clear') {
	unlink('stat_'.$chat_id.'.csv');
	sendMessage($chat_id, 'Your CSV is no more\.');
}
//----------------------------------------------------------------------------------------------------------------------------------//

//отправка форматированного сообщения
function sendMessage($chat_id, $message) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&parse_mode=MarkdownV2');
	curl_exec($ch);
	curl_close($ch);
}

function updateMessage($chat_id, $message_id, $message) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $GLOBALS['api'].'/editMessageText?chat_id='.$chat_id.'&message_id='.$message_id.'&text='.urlencode($message).'&parse_mode=MarkdownV2');
	curl_exec($ch);
	curl_close($ch);
}

function sendDice($chat_id) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $GLOBALS['api'].'/sendDice?chat_id='.$chat_id);
	curl_exec($ch);
	curl_close($ch);
}

function sendFile($chat_id) {
	$filepath = realpath('stat_'.$chat_id.'.csv');
	$post_data = array(
		'chat_id' => $chat_id,
		'document' => new CURLFile($filepath)
	);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $GLOBALS['api'].'/sendDocument');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		"Content-Type:multipart/form-data"
	));
	curl_exec($ch);
	curl_close($ch);
}

mysqli_close($db);
echo "End script.";
?>