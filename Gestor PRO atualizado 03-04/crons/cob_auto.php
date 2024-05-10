<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../db/Conexao.php';
require_once __DIR__ . '/../master/classes/functions.php';

function sendCurlRequest($url, $token, $data) {
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'Authorization: Bearer ' . $token,
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);

  return $response;
}

function sendTextMessageWhats($urlapi, $tokenapi, $phone, $textomsg, $apikey) {
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => $urlapi . "/message/sendText/AbC123" . $tokenapi,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => '{
      "number": "55' . $phone . '",
      "options": {
        "delay": 1200,
        "presence": "composing",
        "linkPreview": false
      },
      "textMessage": {
        "text": "' . $textomsg . '"
      }
    }',
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'apikey: ' . $apikey . ''
    )
  ));

  curl_exec($curl);

  curl_close($curl);
}

function sendMediaMessageWhats($urlapi, $tokenapi, $phone, $caption, $base64, $apikey) {
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => $urlapi . "/message/sendMedia/AbC123" . $tokenapi,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => '{
      "number": "55' . $phone . '",
      "options": {
        "delay": 1200,
        "presence": "composing"
      },
      "mediaMessage": {
        "mediatype": "image",
        "caption": "' . $caption . '",
        "media": "' . $base64 . '"
      }
    }',
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'apikey: ' . $apikey . ''
    )
  ));

  curl_exec($curl);

  curl_close($curl);
}

function sendCobAuto($notifyHour, $actualHour, $msg, $urlapi, $tokenapi, $phone, $messageToSend, $apikey, $paymentType, $connect, $idcli, $installment, $firstName, $lastName, $tokenmp, $msgpix, $msgqr) {
  $qrcode_base64 = "";
  $emv = "";

  if ($notifyHour == $actualHour) {
    if ($msg == "1") {
      sendTextMessageWhats($urlapi, $tokenapi, $phone, $messageToSend, $apikey);
    }

    if ($paymentType == "1") {
      $checkQuery = $connect->prepare("SELECT qrcode, linhad FROM mercadopago WHERE idc = :idcli AND status = 'pending' AND DATE(data) >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)");
      $checkQuery->execute(['idcli' => $idcli]);
      $existingRecord = $checkQuery->fetch(PDO::FETCH_ASSOC);

      $qrcode_base64 = $existingRecord['qrcode'];

      $emv = $existingRecord['linhad'];

      if ($msgpix == "1") {
        sendMediaMessageWhats($urlapi, $tokenapi, $phone, "Pague agora via pix. Leia o QRCode.", $qrcode_base64, $apikey);
      }

      if ($msgqr == "1") {
        sendTextMessageWhats($urlapi, $tokenapi, $phone, $emv, $apikey);
      }
    }

    $msfg = "*ATENÇÃO* Esta é uma mensagem automática e não precisa ser respondida.\\n*Caso já tenha efetuado o pagamento por favor desconsidere esta cobrança.*";

    sendTextMessageWhats($urlapi, $tokenapi, $phone, $msfg, $apikey);
  }
}

$currentDate = date("Ymd");
$dueDate = date("Ymd", strtotime("+5 days", strtotime($currentDate)));
$beforeDate = date("Ymd", strtotime("-3 days", strtotime($currentDate)));

$actualHour = date('H:i');

$query = "SELECT * FROM financeiro2 WHERE pagoem = 'n'";
$payments = $connect->query($query);

while ($paymentsRow = $payments->fetch(PDO::FETCH_OBJ)) {
  $dateFormated = DateTime::createFromFormat('d/m/Y', $paymentsRow->datapagamento)->format('Ymd');

  if ($dateFormated >= $beforeDate && $dateFormated <= $dueDate) {
    $wallet = $connect->query("SELECT * FROM carteira WHERE Id = '" . $paymentsRow->idm . "'");
    $walletRow = $wallet->fetch(PDO::FETCH_OBJ);

    $tokenapi = $walletRow->tokenapi;
    $token = $walletRow->vjurus;
    $tokenmp = $walletRow->tokenmp;
    $company = $walletRow->nomecom;
    $cnpj = $walletRow->cnpj;
    $address = $walletRow->enderecom;
    $phone = $walletRow->contato;
    $msg = $walletRow->msg;
    $msgqr = $walletRow->msgqr;
    $msgpix = $walletRow->msgpix;

    $paymentType = $walletRow->pagamentos;

    $clients = $connect->query("SELECT * FROM clientes WHERE Id='" . $paymentsRow->idc . "'");
    $clientsRow = $clients->fetch(PDO::FETCH_OBJ);

    if ($clientsRow) {
      $name = explode(" ", $clientsRow->nome);
      $firstName = $name[0];
      $lastName = end($name);
      $phone = $clientsRow->celular;
      $idcli = $clientsRow->Id;

      $installment = $paymentsRow->parcela;
      $idcob = $paymentsRow->Id;
      $paymentDate = $paymentsRow->datapagamento;

      $bytes = random_bytes(16);
      $idempotency = bin2hex($bytes);

      $mercadopagoQuery = "SELECT * FROM mercadopago WHERE instancia = ". $idcob;
      $mercadopago = $connect->query($mercadopagoQuery);
      $mercadopagoRow = $mercadopago->fetch(PDO::FETCH_OBJ);

      if ($mercadopagoRow) {
        $linkcob = "/pagamento/?idCob=" . $mercadopagoRow->id . "&idInst=". $idcob . "&idFin=" . $idcob;

        $messages1 = $connect->query("SELECT * FROM mensagens WHERE tipo = '1' AND idu = '" . $paymentsRow->idm . "'");
        $messages1Row = $messages1->fetch(PDO::FETCH_OBJ);

        $search = array('#NOME#', '#VENCIMENTO#', '#VALOR#', '#LINK#', '#EMPRESA#', '#CNPJ#', '#ENDERECO#', '#CONTATO#');
        $replace = array($firstName . " " . $lastName, $paymentDate, $installment, $linkcob, $company, $cnpj, $address, $phone);
        $message = str_replace($search, $replace, $messages1Row->msg);

        $messageToSend = str_replace("\n", "\\n", $message);

        if ($dateFormated == date("Ymd", strtotime("+5 days", strtotime(date("Ymd"))))) {
          sendCobAuto($messages1Row->hora, $actualHour, $msg, $urlapi, $tokenapi, $phone, $messageToSend, $apikey, $paymentType, $connect, $idcli, $installment, $firstName, $lastName, $tokenmp, $msgpix, $msgqr);
        }

        $messages2 = $connect->query("SELECT * FROM mensagens WHERE tipo = '2' AND idu = '" . $paymentsRow->idm . "'");
        $messages2Row = $messages2->fetch(PDO::FETCH_OBJ);

        $search = array('#NOME#', '#VENCIMENTO#', '#VALOR#', '#LINK#', '#EMPRESA#', '#CNPJ#', '#ENDERECO#', '#CONTATO#');
        $replace = array($firstName . " " . $lastName, $paymentDate, $installment, $linkcob, $company, $cnpj, $address, $phone);
        $message = str_replace($search, $replace, $messages2Row->msg);

        $messageToSend = str_replace("\n", "\\n", $message);

        if ($dateFormated == date("Ymd", strtotime("+3 days", strtotime(date("Ymd"))))) {
          sendCobAuto($messages2Row->hora, $actualHour, $msg, $urlapi, $tokenapi, $phone, $messageToSend, $apikey, $paymentType, $connect, $idcli, $installment, $firstName, $lastName, $tokenmp, $msgpix, $msgqr);
        }

        $messages3 = $connect->query("SELECT * FROM mensagens WHERE tipo = '3' AND idu = '" . $paymentsRow->idm . "'");
        $messages3Row = $messages3->fetch(PDO::FETCH_OBJ);

        $search = array('#NOME#', '#VENCIMENTO#', '#VALOR#', '#LINK#', '#EMPRESA#', '#CNPJ#', '#ENDERECO#', '#CONTATO#');
        $replace = array($firstName . " " . $lastName, $paymentDate, $installment, $linkcob, $company, $cnpj, $address, $phone);
        $message = str_replace($search, $replace, $messages3Row->msg);

        $messageToSend = str_replace("\n", "\\n", $message);

        if ($dateFormated == date("Ymd")) {
          sendCobAuto($messages3Row->hora, $actualHour, $msg, $urlapi, $tokenapi, $phone, $messageToSend, $apikey, $paymentType, $connect, $idcli, $installment, $firstName, $lastName, $tokenmp, $msgpix, $msgqr);
        }

        $messages4 = $connect->query("SELECT * FROM mensagens WHERE tipo = '4' AND idu = '" . $paymentsRow->idm . "'");
        $messages4Row = $messages4->fetch(PDO::FETCH_OBJ);

        $search = array('#NOME#', '#VENCIMENTO#', '#VALOR#', '#LINK#', '#EMPRESA#', '#CNPJ#', '#ENDERECO#', '#CONTATO#');
        $replace = array($firstName . " " . $lastName, $paymentDate, $installment, $linkcob, $company, $cnpj, $address, $phone);
        $message = str_replace($search, $replace, $messages4Row->msg);

        $messageToSend = str_replace("\n", "\\n", $message);

        if ($dateFormated < date("Ymd")) {
          sendCobAuto($messages4Row->hora, $actualHour, $msg, $urlapi, $tokenapi, $phone, $messageToSend, $apikey, $paymentType, $connect, $idcli, $installment, $firstName, $lastName, $tokenmp, $msgpix, $msgqr);
        }
      }
    }
  }
}
