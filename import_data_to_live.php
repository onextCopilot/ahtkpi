<?php
/**
 * Budget Data Importer for Live Site
 * Run this on your LIVE server to import Q1 2026 data.
 * DELETE THIS FILE AFTER RUNNING.
 */
require_once __DIR__ . "/config/config.php";

echo "<h2>Importing Budget Data for Q1 2026...</h2>";

$structure_data = array (
  0 => 
  array (
    'id' => '1',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Kinh doanh',
    'category' => '',
    'item_name' => 'Kinh doanh',
    'owner' => '',
    'type' => 'division',
    'order_num' => '1',
  ),
  1 => 
  array (
    'id' => '2',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Kinh doanh',
    'category' => 'BD Holdings',
    'item_name' => 'BD Holdings',
    'owner' => 'Martin Nguyen',
    'type' => 'category',
    'order_num' => '2',
  ),
  2 => 
  array (
    'id' => '3',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Kinh doanh',
    'category' => 'BD Holdings',
    'item_name' => 'MKT Holdings',
    'owner' => 'Martin Nguyen',
    'type' => 'item',
    'order_num' => '3',
  ),
  3 => 
  array (
    'id' => '4',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Kinh doanh',
    'category' => 'BD Holdings',
    'item_name' => 'BD VN Consulting + ITO',
    'owner' => 'Martin',
    'type' => 'item',
    'order_num' => '4',
  ),
  4 => 
  array (
    'id' => '5',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Kinh doanh',
    'category' => 'BD Holdings',
    'item_name' => 'BD MY',
    'owner' => 'Martin',
    'type' => 'item',
    'order_num' => '5',
  ),
  5 => 
  array (
    'id' => '6',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Kinh doanh',
    'category' => 'Digital Sales',
    'item_name' => 'Digital Sales',
    'owner' => 'Martin',
    'type' => 'category',
    'order_num' => '6',
  ),
  6 => 
  array (
    'id' => '7',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Kinh doanh',
    'category' => 'Digital Sales',
    'item_name' => 'Sales Inbound',
    'owner' => 'Martin',
    'type' => 'item',
    'order_num' => '7',
  ),
  7 => 
  array (
    'id' => '8',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => '',
    'item_name' => 'BOD, BO & Chi phí chung',
    'owner' => '',
    'type' => 'division',
    'order_num' => '10',
  ),
  8 => 
  array (
    'id' => '9',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => '- BO & chi phí chung',
    'owner' => '',
    'type' => 'category',
    'order_num' => '11',
  ),
  9 => 
  array (
    'id' => '10',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Nhân sự Back Office',
    'owner' => 'Hương',
    'type' => 'item',
    'order_num' => '12',
  ),
  10 => 
  array (
    'id' => '11',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'BD ITO E (SEA)',
    'owner' => 'Martin',
    'type' => 'item',
    'order_num' => '13',
  ),
  11 => 
  array (
    'id' => '12',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'BD VN Consulting TK & Đi lại',
    'owner' => 'Whale',
    'type' => 'item',
    'order_num' => '14',
  ),
  12 => 
  array (
    'id' => '13',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Commission',
    'owner' => 'Martin',
    'type' => 'item',
    'order_num' => '15',
  ),
  13 => 
  array (
    'id' => '14',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Chi phí phân bổ',
    'owner' => 'Martin',
    'type' => 'item',
    'order_num' => '16',
  ),
  14 => 
  array (
    'id' => '15',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Sales outbound',
    'owner' => 'Martin',
    'type' => 'item',
    'order_num' => '17',
  ),
  15 => 
  array (
    'id' => '16',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'AM',
    'owner' => 'Martin',
    'type' => 'item',
    'order_num' => '18',
  ),
  16 => 
  array (
    'id' => '17',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Comisssion',
    'owner' => 'Martin',
    'type' => 'item',
    'order_num' => '19',
  ),
  17 => 
  array (
    'id' => '18',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Chi phí phân bổ',
    'owner' => 'Martin',
    'type' => 'item',
    'order_num' => '20',
  ),
  18 => 
  array (
    'id' => '19',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Chi phí Admin',
    'owner' => 'Hương',
    'type' => 'item',
    'order_num' => '21',
  ),
  19 => 
  array (
    'id' => '20',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Phúc lợi nhân sự',
    'owner' => 'Hương',
    'type' => 'item',
    'order_num' => '22',
  ),
  20 => 
  array (
    'id' => '21',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Chi phí cố định văn phòng',
    'owner' => 'Hương',
    'type' => 'item',
    'order_num' => '23',
  ),
  21 => 
  array (
    'id' => '22',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Chi phí Thuế DN',
    'owner' => '',
    'type' => 'item',
    'order_num' => '24',
  ),
  22 => 
  array (
    'id' => '23',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Hạ tầng',
    'owner' => 'Steve',
    'type' => 'item',
    'order_num' => '25',
  ),
  23 => 
  array (
    'id' => '24',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Chi phí khác',
    'owner' => 'Hương',
    'type' => 'item',
    'order_num' => '26',
  ),
  24 => 
  array (
    'id' => '25',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'BOD, BO & Chi phí chung',
    'category' => 'BO & chi phí chung',
    'item_name' => 'Summer trip 2025',
    'owner' => 'Hương',
    'type' => 'item',
    'order_num' => '27',
  ),
  25 => 
  array (
    'id' => '26',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => '',
    'item_name' => 'Khối Delivery (Sản xuất)',
    'owner' => '',
    'type' => 'division',
    'order_num' => '28',
  ),
  26 => 
  array (
    'id' => '27',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC1 - AHT Tech',
    'item_name' => 'BC1 - AHT Tech',
    'owner' => '',
    'type' => 'category',
    'order_num' => '29',
  ),
  27 => 
  array (
    'id' => '32',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC2 - AHT Tech',
    'item_name' => 'BC2 - AHT Tech',
    'owner' => '',
    'type' => 'category',
    'order_num' => '34',
  ),
  28 => 
  array (
    'id' => '35',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC5 - AHT Tech (Onext)',
    'item_name' => 'BC5 - AHT Tech (Onext)',
    'owner' => 'Hyun Cao',
    'type' => 'category',
    'order_num' => '37',
  ),
  29 => 
  array (
    'id' => '36',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC6 - A1C VN',
    'item_name' => '- BC6 - A1C VN',
    'owner' => '',
    'type' => 'category',
    'order_num' => '38',
  ),
  30 => 
  array (
    'id' => '37',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC7 - A1C HCM',
    'item_name' => 'BC7 - A1C HCM',
    'owner' => '',
    'type' => 'category',
    'order_num' => '39',
  ),
  31 => 
  array (
    'id' => '38',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC8 - A1C MY',
    'item_name' => '- BC8 - A1C MY',
    'owner' => '',
    'type' => 'category',
    'order_num' => '40',
  ),
  32 => 
  array (
    'id' => '39',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'Khác',
    'item_name' => '- Khác',
    'owner' => '',
    'type' => 'category',
    'order_num' => '41',
  ),
  33 => 
  array (
    'id' => '40',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'Khác',
    'item_name' => 'Chi phí Vendor',
    'owner' => '',
    'type' => 'item',
    'order_num' => '42',
  ),
  34 => 
  array (
    'id' => '41',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'Khác',
    'item_name' => 'Chi phí Partnership',
    'owner' => '',
    'type' => 'item',
    'order_num' => '43',
  ),
  35 => 
  array (
    'id' => '42',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'Khác',
    'item_name' => 'Chi phí Trading (License)',
    'owner' => '',
    'type' => 'item',
    'order_num' => '44',
  ),
  36 => 
  array (
    'id' => '48',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC5 - AHT Tech (Onext)',
    'item_name' => 'BC5',
    'owner' => 'Hyun Cao',
    'type' => 'item',
    'order_num' => '45',
  ),
  37 => 
  array (
    'id' => '49',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC6 - A1C VN',
    'item_name' => 'BC6',
    'owner' => '',
    'type' => 'item',
    'order_num' => '46',
  ),
  38 => 
  array (
    'id' => '50',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC7 - A1C HCM',
    'item_name' => 'BC7',
    'owner' => '',
    'type' => 'item',
    'order_num' => '47',
  ),
  39 => 
  array (
    'id' => '51',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC8 - A1C MY',
    'item_name' => 'BC8',
    'owner' => '',
    'type' => 'item',
    'order_num' => '48',
  ),
  40 => 
  array (
    'id' => '52',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC1 - AHT Tech',
    'item_name' => 'BC1',
    'owner' => 'Vu Dinh Long',
    'type' => 'item',
    'order_num' => '49',
  ),
  41 => 
  array (
    'id' => '55',
    'year' => '2026',
    'quarter' => '1',
    'division' => 'Khối Delivery (Sản xuất)',
    'category' => 'BC2 - AHT Tech',
    'item_name' => 'BC2',
    'owner' => '',
    'type' => 'item',
    'order_num' => '50',
  ),
);
$values_data = array (
  0 => 
  array (
    'id' => '33',
    'item_id' => '2',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '0.00',
  ),
  1 => 
  array (
    'id' => '35',
    'item_id' => '3',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '8.00',
  ),
  2 => 
  array (
    'id' => '37',
    'item_id' => '2',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_salary',
    'amount' => '0.00',
  ),
  3 => 
  array (
    'id' => '38',
    'item_id' => '2',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_other',
    'amount' => '0.00',
  ),
  4 => 
  array (
    'id' => '39',
    'item_id' => '2',
    'year' => '2026',
    'quarter' => '1',
    'month' => '2',
    'value_type' => 'actual_salary',
    'amount' => '0.00',
  ),
  5 => 
  array (
    'id' => '40',
    'item_id' => '2',
    'year' => '2026',
    'quarter' => '1',
    'month' => '3',
    'value_type' => 'actual_salary',
    'amount' => '0.00',
  ),
  6 => 
  array (
    'id' => '41',
    'item_id' => '2',
    'year' => '2026',
    'quarter' => '1',
    'month' => '2',
    'value_type' => 'actual_other',
    'amount' => '0.00',
  ),
  7 => 
  array (
    'id' => '42',
    'item_id' => '2',
    'year' => '2026',
    'quarter' => '1',
    'month' => '3',
    'value_type' => 'actual_other',
    'amount' => '0.00',
  ),
  8 => 
  array (
    'id' => '51',
    'item_id' => '4',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '5.00',
  ),
  9 => 
  array (
    'id' => '52',
    'item_id' => '5',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '6.00',
  ),
  10 => 
  array (
    'id' => '53',
    'item_id' => '7',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '4.00',
  ),
  11 => 
  array (
    'id' => '61',
    'item_id' => '3',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_salary',
    'amount' => '1.00',
  ),
  12 => 
  array (
    'id' => '62',
    'item_id' => '4',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_salary',
    'amount' => '1.00',
  ),
  13 => 
  array (
    'id' => '63',
    'item_id' => '5',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_salary',
    'amount' => '1.00',
  ),
  14 => 
  array (
    'id' => '64',
    'item_id' => '7',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_salary',
    'amount' => '1.00',
  ),
  15 => 
  array (
    'id' => '65',
    'item_id' => '10',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  16 => 
  array (
    'id' => '66',
    'item_id' => '11',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  17 => 
  array (
    'id' => '67',
    'item_id' => '12',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  18 => 
  array (
    'id' => '68',
    'item_id' => '13',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '10.00',
  ),
  19 => 
  array (
    'id' => '69',
    'item_id' => '14',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  20 => 
  array (
    'id' => '70',
    'item_id' => '15',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  21 => 
  array (
    'id' => '71',
    'item_id' => '16',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  22 => 
  array (
    'id' => '72',
    'item_id' => '17',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  23 => 
  array (
    'id' => '73',
    'item_id' => '18',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  24 => 
  array (
    'id' => '74',
    'item_id' => '19',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  25 => 
  array (
    'id' => '75',
    'item_id' => '20',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  26 => 
  array (
    'id' => '76',
    'item_id' => '22',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  27 => 
  array (
    'id' => '105',
    'item_id' => '21',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '-1.00',
  ),
  28 => 
  array (
    'id' => '108',
    'item_id' => '23',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  29 => 
  array (
    'id' => '109',
    'item_id' => '24',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  30 => 
  array (
    'id' => '110',
    'item_id' => '25',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '1.00',
  ),
  31 => 
  array (
    'id' => '112',
    'item_id' => '10',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_salary',
    'amount' => '1.00',
  ),
  32 => 
  array (
    'id' => '116',
    'item_id' => '11',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_salary',
    'amount' => '2.00',
  ),
  33 => 
  array (
    'id' => '117',
    'item_id' => '10',
    'year' => '2026',
    'quarter' => '1',
    'month' => '2',
    'value_type' => 'actual_salary',
    'amount' => '2.00',
  ),
  34 => 
  array (
    'id' => '118',
    'item_id' => '11',
    'year' => '2026',
    'quarter' => '1',
    'month' => '2',
    'value_type' => 'actual_salary',
    'amount' => '2.00',
  ),
  35 => 
  array (
    'id' => '119',
    'item_id' => '11',
    'year' => '2026',
    'quarter' => '1',
    'month' => '2',
    'value_type' => 'actual_other',
    'amount' => '11.00',
  ),
  36 => 
  array (
    'id' => '120',
    'item_id' => '11',
    'year' => '2026',
    'quarter' => '1',
    'month' => '3',
    'value_type' => 'actual_salary',
    'amount' => '312.00',
  ),
  37 => 
  array (
    'id' => '124',
    'item_id' => '4',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_other',
    'amount' => '1.00',
  ),
  38 => 
  array (
    'id' => '125',
    'item_id' => '3',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_other',
    'amount' => '1.00',
  ),
  39 => 
  array (
    'id' => '127',
    'item_id' => '5',
    'year' => '2026',
    'quarter' => '1',
    'month' => '2',
    'value_type' => 'actual_salary',
    'amount' => '1.00',
  ),
  40 => 
  array (
    'id' => '128',
    'item_id' => '21',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_salary',
    'amount' => '1.00',
  ),
  41 => 
  array (
    'id' => '132',
    'item_id' => '40',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '5.00',
  ),
  42 => 
  array (
    'id' => '134',
    'item_id' => '42',
    'year' => '2026',
    'quarter' => '1',
    'month' => '0',
    'value_type' => 'planned',
    'amount' => '2.00',
  ),
  43 => 
  array (
    'id' => '150',
    'item_id' => '7',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_other',
    'amount' => '2.00',
  ),
  44 => 
  array (
    'id' => '151',
    'item_id' => '7',
    'year' => '2026',
    'quarter' => '1',
    'month' => '2',
    'value_type' => 'actual_salary',
    'amount' => '2.00',
  ),
  45 => 
  array (
    'id' => '152',
    'item_id' => '7',
    'year' => '2026',
    'quarter' => '1',
    'month' => '3',
    'value_type' => 'actual_salary',
    'amount' => '2.00',
  ),
  46 => 
  array (
    'id' => '153',
    'item_id' => '16',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_salary',
    'amount' => '1.00',
  ),
  47 => 
  array (
    'id' => '154',
    'item_id' => '16',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_other',
    'amount' => '2.00',
  ),
  48 => 
  array (
    'id' => '155',
    'item_id' => '16',
    'year' => '2026',
    'quarter' => '1',
    'month' => '2',
    'value_type' => 'actual_salary',
    'amount' => '2.00',
  ),
  49 => 
  array (
    'id' => '156',
    'item_id' => '16',
    'year' => '2026',
    'quarter' => '1',
    'month' => '2',
    'value_type' => 'actual_other',
    'amount' => '3.00',
  ),
  50 => 
  array (
    'id' => '157',
    'item_id' => '16',
    'year' => '2026',
    'quarter' => '1',
    'month' => '3',
    'value_type' => 'actual_salary',
    'amount' => '4.00',
  ),
  51 => 
  array (
    'id' => '158',
    'item_id' => '16',
    'year' => '2026',
    'quarter' => '1',
    'month' => '3',
    'value_type' => 'actual_other',
    'amount' => '3.00',
  ),
  52 => 
  array (
    'id' => '160',
    'item_id' => '13',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_salary',
    'amount' => '1.00',
  ),
  53 => 
  array (
    'id' => '161',
    'item_id' => '13',
    'year' => '2026',
    'quarter' => '1',
    'month' => '1',
    'value_type' => 'actual_other',
    'amount' => '1.00',
  ),
  54 => 
  array (
    'id' => '162',
    'item_id' => '13',
    'year' => '2026',
    'quarter' => '1',
    'month' => '2',
    'value_type' => 'actual_salary',
    'amount' => '1.00',
  ),
  55 => 
  array (
    'id' => '163',
    'item_id' => '13',
    'year' => '2026',
    'quarter' => '1',
    'month' => '2',
    'value_type' => 'actual_other',
    'amount' => '1.00',
  ),
  56 => 
  array (
    'id' => '164',
    'item_id' => '13',
    'year' => '2026',
    'quarter' => '1',
    'month' => '3',
    'value_type' => 'actual_salary',
    'amount' => '1.00',
  ),
  57 => 
  array (
    'id' => '165',
    'item_id' => '13',
    'year' => '2026',
    'quarter' => '1',
    'month' => '3',
    'value_type' => 'actual_other',
    'amount' => '4.00',
  ),
);

// Disable FK checks
$conn->query("SET FOREIGN_KEY_CHECKS = 0;");

// Clear existing Q1 data on live to avoid duplicates
$conn->query("DELETE FROM budget_values WHERE year=2026 AND quarter=1");
$conn->query("DELETE FROM budget_structure WHERE year=2026 AND quarter=1");

$id_map = []; // Map old ID to new ID if needed, but since we use INSERT with IDs, it should be fine.

// 1. Insert Structure
echo "<h3>1. Importing Structure...</h3>";
foreach ($structure_data as $row) {
    $keys = array_keys($row);
    $vals = array_map(function($v) use ($conn) { return is_null($v) ? "NULL" : "'" . $conn->real_escape_string($v) . "'"; }, array_values($row));
    
    // Simple manual SQL for import script safety
    $sql = "INSERT INTO budget_structure (" . implode(",", $keys) . ") VALUES (" . implode(",", $vals) . ")";
    if ($conn->query($sql)) {
        echo "✅ Imported: " . ($row["item_name"] ?: "No name") . "<br>";
    } else {
        echo "❌ Error: " . $conn->error . "<br>";
    }
}

// 2. Insert Values
echo "<h3>2. Importing Values...</h3>";
foreach ($values_data as $row) {
    $keys = array_keys($row);
    $vals = array_map(function($v) use ($conn) { return is_null($v) ? "NULL" : "'" . $conn->real_escape_string($v) . "'"; }, array_values($row));
    
    $sql = "INSERT INTO budget_values (" . implode(",", $keys) . ") VALUES (" . implode(",", $vals) . ")";
    if ($conn->query($sql)) {
        // success
    } else {
        echo "❌ Error (Values): " . $conn->error . "<br>";
    }
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
echo "<hr><p style='color:green; font-weight:bold;'>DONE! Data imported successfully.</p>";
echo "<p>Please delete this file from your live server now.</p>";
?>