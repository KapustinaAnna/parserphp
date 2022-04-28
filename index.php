<?php
header('Content-type:text/html; charset=UTF-8');
setlocale(LC_ALL, 'ru_RU.UTF-8');
ini_set('error_reporting', E_ALL);
ini_set('display_erroros', 1);
ini_set('display_start_errors', 1);

/**Подключаем бибилиотеки*/
require __DIR__ . '\phpQuery-onefile.php';
require __DIR__ . '\PHPExcel-1.8\Classes\PHPExcel.php';
require __DIR__ . '\PHPExcel-1.8\Classes\PHPExcel\Writer\Excel2007.php';

/**парсинг данных */
function parser($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $rezult = curl_exec($ch);
    curl_close($ch);
    return $rezult;
}
/** помещаем картинки одного товара в массив*/
function listParam($listParamProd)
{
    foreach ($listParamProd as $param) {
        $elemParam = pq($param);
        $arrElemParam[] = $elemParam->text();
    }
    return $arrElemParam;
}
/** Получили картинки и сохранили в папку главную*/
function parser_img($imgUrl, $imgName)
{
    $img = file_get_contents("https://podtrade.ru" . $imgUrl);
    file_put_contents(__DIR__ . "/upload/" . $imgName, $img);
}
/**получили ссылки всех товаров */
for ($i = 0; $i <= 6; $i++) {
    if ($i === 1) {
        $urlCatalog = "https://podtrade.ru/catalog/01_sharikovye_podshipniki/";
    } else {
          $urlCatalog = "https://podtrade.ru/catalog/01_sharikovye_podshipniki/?PAGEN_1=" . $i;
  }
    $rezult =  parser($urlCatalog);
    $pq = phpQuery::newDocument($rezult);
    $listParamProduct = $pq->find(".trigran__item__title");
    foreach ($listParamProduct as $link) {
        $elemlink = pq($link);
        $arrLinks[] = "https://podtrade.ru" . $elemlink->attr("href");
    }
}
/**Удалили повторяющиеся ссылки */
$arrLinksUniq = array_unique($arrLinks);

/*При не первом запуски программы для увеличения скорости работы лучше сохранить спарсенные данные в файл
отправили в  файл
$jsonProductUrl = json_encode($arrLinks);
file_put_contents("dataUrl.txt",$jsonProductUrl);*/

/*взяли из файла
$jsonProductUrl =file_get_contents("dataUrl.txt");
$arrLinks = json_decode($jsonProductUrl,true);*/

/**забираем данные товаров */
foreach ($arrLinksUniq as $link) {
    $rezult =  parser($link);
    $pq = phpQuery::newDocument($rezult);
    $listParam = $pq->find(".trigran__detail_harakteristics_container td");
    $linkParam = $pq->find(".product-params__cell a.href_opisanie")->attr("href");
    $arrMainParam = listParam($listParam);
    $listImg = $pq->find("img.swiper-img");
    $i = 0;
  /** удаляем путь до картинки */
    foreach ($listImg as $img) {
        $elemImg = pq($img);
        $arrImg[$i] = $elemImg->attr("src");
        $number = strrpos($arrImg[$i], "/") + 1;
        $imgName[$i] = substr($arrImg[$i], $number);
        $i++;
    };
    /**оправляем картинки в папку */
    parser_img($arrImg[0], $imgName[0]);
    /**проверка на наличие данных*/
    if (!isset($arrMainParam[5])) {
        $arrMainParam[5] = "";
    };
    if (!isset($arrMainParam[4])) {
        $arrMainParam[4] = "";
    };
  /**формируем массив параметров*/
    $arrParam = [
        "url" => $link,
        "name" => $pq->find("h1")->text(),
        "img" => $imgName[0],
        "price" => preg_replace("/[^0-9]*/", "", $pq->find(".product-item-detail-price-current")->text()),
        "weight" => $arrMainParam[0],
        "vnytrdiametr" =>   $arrMainParam[1],
        "vneshniiD" =>   $arrMainParam[2],
        "width" =>   $arrMainParam[3],
        "brend" =>  $arrMainParam[4],
        "desc" =>  trim($arrMainParam[5]) . '  ' . $linkParam,
    ];
  $arrproduct[] = [
      "product" => $arrParam,
  ];
}

/**создаем объект excel */
$xls = new PHPExcel();
$sheet = $xls->getActiveSheet();
$sheet->setTitle('Товара Каталога');
$sheet->setCellValue('A1', "URL ");
$sheet->setCellValue('B1', "Наименование");
$sheet->setCellValue('C1', "Наименование главного изображения");
$sheet->setCellValue('D1', "Стоимость");
$sheet->setCellValue('E1', "Вес");
$sheet->setCellValue('F1', "Внутренний диаметр (d)");
$sheet->setCellValue('G1', "Внешний диаметр (D)");
$sheet->setCellValue('H1', "Ширина B (H)");
$sheet->setCellValue('I1', "Бренд");
$sheet->setCellValue('J1', "Техническое описание");
/**отправляем параметры товара*/
foreach ($arrproduct as $key => $productelem) {
  $index = $key + 2;
  $product = $productelem["product"];
  $sheet->setCellValue('A' . $index, $product["url"]);
  $sheet->setCellValue('B' . $index, $product["name"]);
  $sheet->setCellValue('C' . $index, $product["img"]);
  $sheet->setCellValue('D' . $index, $product["price"]);
  $sheet->setCellValue('E' . $index, $product["weight"]);
  $sheet->setCellValue('F' . $index, $product["vnytrdiametr"]);
  $sheet->setCellValue('G' . $index, $product["vneshniiD"]);
  $sheet->setCellValue('H' . $index, $product["width"]);
  $sheet->setCellValue('I' . $index, $product["brend"]);
  $sheet->setCellValue('J' . $index, $product["desc"]);
}
/**помещаем данные в файл */
$objWriter = new PHPExcel_Writer_Excel2007($xls);
$filePath = __DIR__ . "/file_catalog.xlsx";
$objWriter->save($filePath);
echo "<p>Все готово можно смотреть результат!</p><p>В папке Upload картинки, товары в file_catalog.xlsx<p/>";
