<?php
require_once __DIR__ . '/config/config.php';
global $conn;
if (!isset($conn) || $conn->connect_error) { die('Connection failed'); }

echo '<h2>Bắt đầu import dữ liệu HRM...</h2><ul>';

if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_candidate_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum(\'internal\',\'external\') COLLATE utf8mb4_unicode_ci DEFAULT \'external\',
  `is_active` tinyint(1) DEFAULT \'1\',
  `sort_order` int(11) DEFAULT \'0\',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_candidate_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum(\&#039;internal\&#039;,\&#039;external\&#039;) COLLATE utf8mb4_unicode_ci DEFAULT \&#039;external\&#039;,
  `is_active` tinyint(1) DEFAULT \&#039;1\&#039;,
  `sort_order` int(11) DEFAULT \&#039;0\&#039;,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_candidate_sources`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_candidate_sources`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'1\', \'ADJOB\', \'external\', \'1\', \'1\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;1\&#039;, \&#039;ADJOB\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;1\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'2\', \'THREADS\', \'external\', \'0\', \'0\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;2\&#039;, \&#039;THREADS\&#039;, \&#039;external\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'3\', \'METWORKING\', \'external\', \'1\', \'2\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;3\&#039;, \&#039;METWORKING\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;2\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'4\', \'SKYPE\', \'external\', \'1\', \'3\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;4\&#039;, \&#039;SKYPE\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;3\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'5\', \'LLINKEDIN\', \'external\', \'1\', \'4\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;5\&#039;, \&#039;LLINKEDIN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;4\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'6\', \'NO\', \'external\', \'1\', \'5\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;6\&#039;, \&#039;NO\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;5\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'7\', \'WEB-TRAINING-AHT\', \'external\', \'1\', \'6\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;7\&#039;, \&#039;WEB-TRAINING-AHT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;6\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'8\', \'BAN\', \'external\', \'1\', \'7\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;8\&#039;, \&#039;BAN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;7\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'9\', \'GETBEE\', \'external\', \'1\', \'8\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;9\&#039;, \&#039;GETBEE\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;8\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'10\', \'NETWORKINH\', \'external\', \'1\', \'9\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;10\&#039;, \&#039;NETWORKINH\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;9\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'11\', \'ANDROID\', \'external\', \'1\', \'10\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;11\&#039;, \&#039;ANDROID\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;10\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'12\', \'FORUM\', \'external\', \'1\', \'11\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;12\&#039;, \&#039;FORUM\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;11\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'13\', \'VIRTUAL-CAREER-FAIR-2021\', \'external\', \'1\', \'12\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;13\&#039;, \&#039;VIRTUAL-CAREER-FAIR-2021\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;12\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'14\', \'JAVA\', \'external\', \'1\', \'13\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;14\&#039;, \&#039;JAVA\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;13\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'15\', \'GITHUB\', \'external\', \'1\', \'14\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;15\&#039;, \&#039;GITHUB\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;14\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'16\', \'LINKDIN\', \'external\', \'1\', \'15\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;16\&#039;, \&#039;LINKDIN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;15\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'17\', \'TESTER\', \'external\', \'1\', \'16\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;17\&#039;, \&#039;TESTER\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;16\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'18\', \'HTTPSWWW.FACEBOOK.COMPROFILE.PHPID100013392615640\', \'external\', \'1\', \'17\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;18\&#039;, \&#039;HTTPSWWW.FACEBOOK.COMPROFILE.PHPID100013392615640\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;17\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'19\', \'SKYPE-TOMCLANCY1234\', \'external\', \'1\', \'18\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;19\&#039;, \&#039;SKYPE-TOMCLANCY1234\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;18\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'20\', \'HTTPSWWW.FACEBOOK.COMELDESPERADO305\', \'external\', \'1\', \'19\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;20\&#039;, \&#039;HTTPSWWW.FACEBOOK.COMELDESPERADO305\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;19\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'21\', \'HTTPSWWW.FACEBOOK.COMCONGNTIT\', \'external\', \'1\', \'20\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;21\&#039;, \&#039;HTTPSWWW.FACEBOOK.COMCONGNTIT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;20\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'22\', \'LINKEIN\', \'external\', \'1\', \'21\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;22\&#039;, \&#039;LINKEIN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;21\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'23\', \'LUONGNT-REFER\', \'internal\', \'1\', \'22\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;23\&#039;, \&#039;LUONGNT-REFER\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;22\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'24\', \'WEB-ONNET\', \'external\', \'1\', \'23\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;24\&#039;, \&#039;WEB-ONNET\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;23\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'25\', \'RECO\', \'external\', \'1\', \'24\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;25\&#039;, \&#039;RECO\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;24\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'26\', \'LINHEB\', \'external\', \'1\', \'25\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;26\&#039;, \&#039;LINHEB\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;25\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'27\', \'HTTPSWWW.FACEBOOK.COMEUGENE.NGUYEN.XB\', \'external\', \'1\', \'26\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;27\&#039;, \&#039;HTTPSWWW.FACEBOOK.COMEUGENE.NGUYEN.XB\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;26\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'28\', \'NW\', \'external\', \'1\', \'27\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;28\&#039;, \&#039;NW\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;27\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'29\', \'CAREER-LINK\', \'external\', \'1\', \'28\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;29\&#039;, \&#039;CAREER-LINK\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;28\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'30\', \'FACEBOOK.-LUONGNT\', \'external\', \'1\', \'29\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;30\&#039;, \&#039;FACEBOOK.-LUONGNT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;29\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'31\', \'ACCOUNT\', \'external\', \'1\', \'30\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;31\&#039;, \&#039;ACCOUNT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;30\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'32\', \'RYTHEMYGMAIL.COM\', \'external\', \'1\', \'31\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;32\&#039;, \&#039;RYTHEMYGMAIL.COM\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;31\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'33\', \'SALES\', \'external\', \'1\', \'32\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;33\&#039;, \&#039;SALES\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;32\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'34\', \'BD\', \'external\', \'1\', \'33\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;34\&#039;, \&#039;BD\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;33\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'35\', \'NULO-2022\', \'external\', \'1\', \'34\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;35\&#039;, \&#039;NULO-2022\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;34\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'36\', \'NGALT\', \'external\', \'1\', \'35\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;36\&#039;, \&#039;NGALT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;35\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'37\', \'GMAIL\', \'external\', \'1\', \'36\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;37\&#039;, \&#039;GMAIL\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;36\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'38\', \'REFER-NETWORKING\', \'internal\', \'1\', \'37\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;38\&#039;, \&#039;REFER-NETWORKING\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;37\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'39\', \'SALES-IT\', \'external\', \'1\', \'38\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;39\&#039;, \&#039;SALES-IT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;38\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'40\', \'THAOPT\', \'external\', \'1\', \'39\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;40\&#039;, \&#039;THAOPT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;39\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'41\', \'CL\', \'external\', \'1\', \'40\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;41\&#039;, \&#039;CL\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;40\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'42\', \'APTECH\', \'external\', \'1\', \'41\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;42\&#039;, \&#039;APTECH\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;41\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'43\', \'DH-FPT\', \'external\', \'1\', \'42\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;43\&#039;, \&#039;DH-FPT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;42\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'44\', \'DHCNHN\', \'external\', \'1\', \'43\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;44\&#039;, \&#039;DHCNHN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;43\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'45\', \'DHBK-TOPCV\', \'external\', \'1\', \'44\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;45\&#039;, \&#039;DHBK-TOPCV\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;44\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'46\', \'DHCNHN-TOPCV\', \'external\', \'1\', \'45\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;46\&#039;, \&#039;DHCNHN-TOPCV\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;45\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'47\', \'CD-CONG-NGHE-THUONG-MAI\', \'external\', \'1\', \'46\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;47\&#039;, \&#039;CD-CONG-NGHE-THUONG-MAI\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;46\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'48\', \'DH-SU-HAM-HN\', \'external\', \'1\', \'47\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;48\&#039;, \&#039;DH-SU-HAM-HN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;47\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'49\', \'DH-MO-HN\', \'external\', \'1\', \'48\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;49\&#039;, \&#039;DH-MO-HN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;48\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'50\', \'WORKVN\', \'external\', \'1\', \'49\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;50\&#039;, \&#039;WORKVN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;49\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'51\', \'T3H\', \'external\', \'1\', \'50\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;51\&#039;, \&#039;T3H\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;50\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'52\', \'NETWRKING\', \'external\', \'1\', \'51\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;52\&#039;, \&#039;NETWRKING\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;51\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'53\', \'TOPCV-FACEBOOK\', \'external\', \'1\', \'52\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;53\&#039;, \&#039;TOPCV-FACEBOOK\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;52\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'54\', \'FAECEBOOK\', \'external\', \'1\', \'53\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;54\&#039;, \&#039;FAECEBOOK\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;53\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'55\', \'AGENCY-WATAJOB\', \'external\', \'1\', \'54\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;55\&#039;, \&#039;AGENCY-WATAJOB\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;54\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'56\', \'NETWOKING\', \'external\', \'1\', \'55\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;56\&#039;, \&#039;NETWOKING\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;55\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'57\', \'NGOCBTT,SALES-IT\', \'external\', \'1\', \'56\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;57\&#039;, \&#039;NGOCBTT,SALES-IT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;56\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'58\', \'TOP-CV\', \'external\', \'1\', \'57\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;58\&#039;, \&#039;TOP-CV\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;57\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'59\', \'REFER-HOANG-ANH-LEAD\', \'internal\', \'1\', \'58\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;59\&#039;, \&#039;REFER-HOANG-ANH-LEAD\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;58\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'60\', \'NETWORL\', \'external\', \'1\', \'59\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;60\&#039;, \&#039;NETWORL\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;59\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'61\', \'HEADHUNT-GETBEE\', \'external\', \'1\', \'60\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;61\&#039;, \&#039;HEADHUNT-GETBEE\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;60\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'62\', \'MAIL-HR\', \'external\', \'1\', \'61\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;62\&#039;, \&#039;MAIL-HR\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;61\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'63\', \'REFERAL\', \'internal\', \'1\', \'62\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;63\&#039;, \&#039;REFERAL\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;62\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'64\', \'WEBINAR\', \'external\', \'1\', \'63\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;64\&#039;, \&#039;WEBINAR\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;63\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'65\', \'TOPCV-APPLY\', \'external\', \'1\', \'64\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;65\&#039;, \&#039;TOPCV-APPLY\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;64\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'66\', \'PAGE\', \'external\', \'1\', \'65\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;66\&#039;, \&#039;PAGE\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;65\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'67\', \'VN\', \'external\', \'1\', \'66\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;67\&#039;, \&#039;VN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;66\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'68\', \'NETWROK\', \'external\', \'1\', \'67\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;68\&#039;, \&#039;NETWROK\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;67\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'69\', \'REFER-CHINH-ASIA\', \'internal\', \'1\', \'68\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;69\&#039;, \&#039;REFER-CHINH-ASIA\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;68\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'70\', \'ITO\', \'external\', \'1\', \'69\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;70\&#039;, \&#039;ITO\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;69\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'71\', \'PHUONGDM-REFER\', \'internal\', \'1\', \'70\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;71\&#039;, \&#039;PHUONGDM-REFER\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;70\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'72\', \'GLINT\', \'external\', \'1\', \'71\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;72\&#039;, \&#039;GLINT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;71\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'73\', \'FACKFRUIT\', \'external\', \'1\', \'72\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;73\&#039;, \&#039;FACKFRUIT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;72\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'74\', \'HEADHUNTER\', \'external\', \'1\', \'73\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;74\&#039;, \&#039;HEADHUNTER\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;73\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'75\', \'HEADHUNT,JACKFRUIT\', \'external\', \'1\', \'74\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;75\&#039;, \&#039;HEADHUNT,JACKFRUIT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;74\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'76\', \'FPT-POLYTECHNIC\', \'external\', \'1\', \'75\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;76\&#039;, \&#039;FPT-POLYTECHNIC\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;75\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'77\', \'DHHN\', \'external\', \'1\', \'76\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;77\&#039;, \&#039;DHHN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;76\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'78\', \'QHDN\', \'external\', \'1\', \'77\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;78\&#039;, \&#039;QHDN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;77\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'79\', \'TRANGLD\', \'external\', \'1\', \'78\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;79\&#039;, \&#039;TRANGLD\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;78\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'80\', \'FACEBOOK,TOPCV\', \'external\', \'1\', \'79\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;80\&#039;, \&#039;FACEBOOK,TOPCV\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;79\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'81\', \'HUNTER-GLINTS\', \'external\', \'1\', \'80\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;81\&#039;, \&#039;HUNTER-GLINTS\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;80\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'82\', \'VIETNAMWORK\', \'external\', \'1\', \'81\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;82\&#039;, \&#039;VIETNAMWORK\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;81\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'83\', \'VNWS\', \'external\', \'1\', \'82\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;83\&#039;, \&#039;VNWS\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;82\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'84\', \'TOPCV,ACCOUNT\', \'external\', \'1\', \'83\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;84\&#039;, \&#039;TOPCV,ACCOUNT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;83\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'85\', \'PREFER-NOI-BO\', \'internal\', \'1\', \'84\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;85\&#039;, \&#039;PREFER-NOI-BO\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;84\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'86\', \'NGOCBTT\', \'external\', \'1\', \'85\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;86\&#039;, \&#039;NGOCBTT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;85\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'87\', \'VNW\', \'external\', \'1\', \'86\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;87\&#039;, \&#039;VNW\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;86\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'88\', \'REFER-JESSIE\', \'internal\', \'1\', \'87\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;88\&#039;, \&#039;REFER-JESSIE\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;87\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'89\', \'PAGE-AHT\', \'external\', \'1\', \'88\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;89\&#039;, \&#039;PAGE-AHT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;88\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'90\', \'MAIL\', \'external\', \'1\', \'89\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;90\&#039;, \&#039;MAIL\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;89\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'91\', \'USTH\', \'external\', \'1\', \'90\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;91\&#039;, \&#039;USTH\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;90\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'92\', \'FACEBOOK,LINKEDIN\', \'external\', \'1\', \'91\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;92\&#039;, \&#039;FACEBOOK,LINKEDIN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;91\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'93\', \'BEHANCE\', \'external\', \'1\', \'92\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;93\&#039;, \&#039;BEHANCE\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;92\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'94\', \'REFER-UYEN-QA\', \'internal\', \'1\', \'93\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;94\&#039;, \&#039;REFER-UYEN-QA\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;93\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'95\', \'REFER-OHIO\', \'internal\', \'1\', \'94\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;95\&#039;, \&#039;REFER-OHIO\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;94\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'96\', \'NETWORING\', \'external\', \'1\', \'95\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;96\&#039;, \&#039;NETWORING\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;95\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'97\', \'WATAJOB\', \'external\', \'1\', \'96\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;97\&#039;, \&#039;WATAJOB\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;96\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'98\', \'DEVWORK\', \'external\', \'1\', \'97\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;98\&#039;, \&#039;DEVWORK\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;97\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'99\', \'JACKFRUIT\', \'external\', \'1\', \'98\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;99\&#039;, \&#039;JACKFRUIT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;98\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'100\', \'LINH-EB\', \'external\', \'1\', \'99\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;100\&#039;, \&#039;LINH-EB\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;99\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'101\', \'OHIO-JESSIE\', \'external\', \'1\', \'100\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;101\&#039;, \&#039;OHIO-JESSIE\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;100\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'102\', \'DUNGIC\', \'external\', \'1\', \'101\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;102\&#039;, \&#039;DUNGIC\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;101\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'103\', \'FACEBOOK,LINHEB\', \'external\', \'1\', \'102\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;103\&#039;, \&#039;FACEBOOK,LINHEB\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;102\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'104\', \'FACEBOOOK\', \'external\', \'1\', \'103\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;104\&#039;, \&#039;FACEBOOOK\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;103\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'105\', \'OHIO\', \'external\', \'1\', \'104\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;105\&#039;, \&#039;OHIO\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;104\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'106\', \'ONNET\', \'external\', \'1\', \'105\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;106\&#039;, \&#039;ONNET\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;105\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'107\', \'REFER-HUYEN-MINH\', \'internal\', \'1\', \'106\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;107\&#039;, \&#039;REFER-HUYEN-MINH\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;106\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'108\', \'DH-CNHN\', \'external\', \'1\', \'107\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;108\&#039;, \&#039;DH-CNHN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;107\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'109\', \'GREENWICH\', \'external\', \'1\', \'108\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;109\&#039;, \&#039;GREENWICH\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;108\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'110\', \'DH-DIEN-LUC\', \'external\', \'1\', \'109\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;110\&#039;, \&#039;DH-DIEN-LUC\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;109\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'111\', \'GOOGLE_JOBS_APPLY\', \'external\', \'1\', \'110\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;111\&#039;, \&#039;GOOGLE_JOBS_APPLY\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;110\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'112\', \'PTIT\', \'external\', \'1\', \'111\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;112\&#039;, \&#039;PTIT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;111\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'113\', \'REFER-PHUONGDM\', \'internal\', \'1\', \'112\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;113\&#039;, \&#039;REFER-PHUONGDM\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;112\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'114\', \'MAILHR\', \'external\', \'1\', \'113\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;114\&#039;, \&#039;MAILHR\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;113\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'115\', \'NGALT-ANGULAR\', \'external\', \'1\', \'114\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;115\&#039;, \&#039;NGALT-ANGULAR\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;114\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'116\', \'ZALO\', \'external\', \'1\', \'115\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;116\&#039;, \&#039;ZALO\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;115\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'117\', \'VIEN-NGOAI-NGU-DH-BK-HN\', \'external\', \'1\', \'116\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;117\&#039;, \&#039;VIEN-NGOAI-NGU-DH-BK-HN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;116\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'118\', \'FB\', \'external\', \'1\', \'117\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;118\&#039;, \&#039;FB\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;117\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'119\', \'NGALT,LINKEDIN\', \'external\', \'1\', \'118\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;119\&#039;, \&#039;NGALT,LINKEDIN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;118\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'120\', \'SHARECV\', \'external\', \'1\', \'119\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;120\&#039;, \&#039;SHARECV\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;119\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'121\', \'LUONGNT\', \'external\', \'1\', \'120\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;121\&#039;, \&#039;LUONGNT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;120\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'122\', \'HEADHUNT\', \'external\', \'1\', \'121\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;122\&#039;, \&#039;HEADHUNT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;121\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'123\', \'HRMAIL\', \'external\', \'1\', \'122\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;123\&#039;, \&#039;HRMAIL\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;122\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'124\', \'DEVPRO\', \'external\', \'1\', \'123\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;124\&#039;, \&#039;DEVPRO\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;123\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'125\', \'PHUONGDM\', \'external\', \'1\', \'124\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;125\&#039;, \&#039;PHUONGDM\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;124\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'126\', \'REFER-LUONGNT\', \'internal\', \'1\', \'125\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;126\&#039;, \&#039;REFER-LUONGNT\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;125\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'127\', \'LINKEIDN\', \'external\', \'1\', \'126\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;127\&#039;, \&#039;LINKEIDN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;126\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'128\', \'REFER-NOI-BO\', \'internal\', \'1\', \'127\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;128\&#039;, \&#039;REFER-NOI-BO\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;127\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'129\', \'REFER\', \'internal\', \'1\', \'128\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;129\&#039;, \&#039;REFER\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;128\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'130\', \'HAUI\', \'external\', \'1\', \'129\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;130\&#039;, \&#039;HAUI\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;129\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'131\', \'NETWORK\', \'external\', \'1\', \'130\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;131\&#039;, \&#039;NETWORK\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;130\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'132\', \'DH-CNHN,UPLOAD\', \'external\', \'1\', \'131\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;132\&#039;, \&#039;DH-CNHN,UPLOAD\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;131\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'133\', \'WEBSITE,UPLOAD\', \'internal\', \'1\', \'132\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;133\&#039;, \&#039;WEBSITE,UPLOAD\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;132\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'134\', \'NETWORKING\', \'external\', \'1\', \'133\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;134\&#039;, \&#039;NETWORKING\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;133\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'135\', \'VIECLAM123\', \'external\', \'1\', \'134\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;135\&#039;, \&#039;VIECLAM123\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;134\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'136\', \'JOBOKO\', \'external\', \'1\', \'135\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;136\&#039;, \&#039;JOBOKO\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;135\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'137\', \'YBOX\', \'external\', \'1\', \'136\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;137\&#039;, \&#039;YBOX\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;136\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'138\', \'TIMVIEC365\', \'external\', \'1\', \'137\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;138\&#039;, \&#039;TIMVIEC365\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;137\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'139\', \'123JOB\', \'external\', \'1\', \'138\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;139\&#039;, \&#039;123JOB\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;138\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'140\', \'INDEED\', \'external\', \'1\', \'139\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;140\&#039;, \&#039;INDEED\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;139\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'141\', \'VIECTOTNHAT\', \'external\', \'1\', \'140\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;141\&#039;, \&#039;VIECTOTNHAT\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;140\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'142\', \'TOPDEV\', \'external\', \'1\', \'141\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;142\&#039;, \&#039;TOPDEV\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;141\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'143\', \'MYWORK\', \'external\', \'1\', \'142\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;143\&#039;, \&#039;MYWORK\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;142\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'144\', \'TOPCV\', \'external\', \'1\', \'143\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;144\&#039;, \&#039;TOPCV\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;143\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'145\', \'CAREERLINK\', \'external\', \'1\', \'144\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;145\&#039;, \&#039;CAREERLINK\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;144\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'146\', \'CAREERBUILDER.VN\', \'external\', \'1\', \'145\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;146\&#039;, \&#039;CAREERBUILDER.VN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;145\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'147\', \'JOBSTREET.VN\', \'external\', \'1\', \'146\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;147\&#039;, \&#039;JOBSTREET.VN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;146\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'148\', \'VIETNAMWORKS\', \'external\', \'1\', \'147\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;148\&#039;, \&#039;VIETNAMWORKS\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;147\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'149\', \'TIMVIECNHANH.COM\', \'external\', \'1\', \'148\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;149\&#039;, \&#039;TIMVIECNHANH.COM\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;148\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'150\', \'JOBSGO\', \'external\', \'1\', \'149\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;150\&#039;, \&#039;JOBSGO\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;149\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'151\', \'VIECLAM24H\', \'external\', \'1\', \'150\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;151\&#039;, \&#039;VIECLAM24H\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;150\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'152\', \'ITVIEC.COM\', \'external\', \'1\', \'151\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;152\&#039;, \&#039;ITVIEC.COM\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;151\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'153\', \'TALENT POOL\', \'external\', \'1\', \'152\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;153\&#039;, \&#039;TALENT POOL\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;152\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'154\', \'EMAIL\', \'external\', \'1\', \'153\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;154\&#039;, \&#039;EMAIL\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;153\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'155\', \'UPLOAD\', \'external\', \'1\', \'154\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;155\&#039;, \&#039;UPLOAD\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;154\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'156\', \'RECRUITER\', \'external\', \'1\', \'155\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;156\&#039;, \&#039;RECRUITER\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;155\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'157\', \'REFERRAL\', \'internal\', \'1\', \'157\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;157\&#039;, \&#039;REFERRAL\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;157\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'158\', \'LINKEDIN\', \'external\', \'1\', \'156\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;158\&#039;, \&#039;LINKEDIN\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;156\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'159\', \'FACEBOOK\', \'external\', \'1\', \'158\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;159\&#039;, \&#039;FACEBOOK\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;158\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'160\', \'WEBSITE\', \'internal\', \'1\', \'159\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;160\&#039;, \&#039;WEBSITE\&#039;, \&#039;internal\&#039;, \&#039;1\&#039;, \&#039;159\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\'161\', \'OTHER\', \'external\', \'1\', \'160\', \'2026-05-06 17:24:36\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_candidate_sources` (`id`, `name`, `type`, `is_active`, `sort_order`, `created_at`) VALUES (\&#039;161\&#039;, \&#039;OTHER\&#039;, \&#039;external\&#039;, \&#039;1\&#039;, \&#039;160\&#039;, \&#039;2026-05-06 17:24:36\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_company_settings` (
  `id` int(11) NOT NULL DEFAULT \'1\',
  `company_name` varchar(255) DEFAULT NULL,
  `company_website` varchar(255) DEFAULT NULL,
  `company_phone` varchar(50) DEFAULT NULL,
  `company_address` text,
  `recruit_title` varchar(255) DEFAULT NULL,
  `recruit_url` varchar(255) DEFAULT NULL,
  `recruit_desc` text,
  `favicon` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `sla_mode` varchar(50) DEFAULT NULL,
  `require_job_code` tinyint(1) DEFAULT \'0\',
  `evaluation_method` varchar(50) DEFAULT \'general\',
  `auto_create_from_email` tinyint(1) DEFAULT \'0\',
  `min_delete_permission` varchar(50) DEFAULT \'admin\',
  `min_export_permission` varchar(50) DEFAULT \'admin\',
  `enable_captcha` tinyint(1) DEFAULT \'0\',
  `email_interview_invitation` int(11) DEFAULT \'0\',
  `email_interview_update` int(11) DEFAULT \'0\',
  `email_interview_cancel` int(11) DEFAULT \'0\',
  `email_interview_bulk` int(11) DEFAULT \'0\',
  `interview_cv_display` varchar(50) DEFAULT \'restricted\',
  `onboard_integration_permission` varchar(50) DEFAULT \'manager\',
  `rejection_reason_mandatory` tinyint(1) DEFAULT \'0\',
  `auto_close_expired` tinyint(1) DEFAULT \'1\',
  `auto_hide_expired` tinyint(1) DEFAULT \'1\',
  `email_before_expiry` tinyint(1) DEFAULT \'1\',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_company_settings` (
  `id` int(11) NOT NULL DEFAULT \&#039;1\&#039;,
  `company_name` varchar(255) DEFAULT NULL,
  `company_website` varchar(255) DEFAULT NULL,
  `company_phone` varchar(50) DEFAULT NULL,
  `company_address` text,
  `recruit_title` varchar(255) DEFAULT NULL,
  `recruit_url` varchar(255) DEFAULT NULL,
  `recruit_desc` text,
  `favicon` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `sla_mode` varchar(50) DEFAULT NULL,
  `require_job_code` tinyint(1) DEFAULT \&#039;0\&#039;,
  `evaluation_method` varchar(50) DEFAULT \&#039;general\&#039;,
  `auto_create_from_email` tinyint(1) DEFAULT \&#039;0\&#039;,
  `min_delete_permission` varchar(50) DEFAULT \&#039;admin\&#039;,
  `min_export_permission` varchar(50) DEFAULT \&#039;admin\&#039;,
  `enable_captcha` tinyint(1) DEFAULT \&#039;0\&#039;,
  `email_interview_invitation` int(11) DEFAULT \&#039;0\&#039;,
  `email_interview_update` int(11) DEFAULT \&#039;0\&#039;,
  `email_interview_cancel` int(11) DEFAULT \&#039;0\&#039;,
  `email_interview_bulk` int(11) DEFAULT \&#039;0\&#039;,
  `interview_cv_display` varchar(50) DEFAULT \&#039;restricted\&#039;,
  `onboard_integration_permission` varchar(50) DEFAULT \&#039;manager\&#039;,
  `rejection_reason_mandatory` tinyint(1) DEFAULT \&#039;0\&#039;,
  `auto_close_expired` tinyint(1) DEFAULT \&#039;1\&#039;,
  `auto_hide_expired` tinyint(1) DEFAULT \&#039;1\&#039;,
  `email_before_expiry` tinyint(1) DEFAULT \&#039;1\&#039;,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_company_settings`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_company_settings`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_company_settings` (`id`, `company_name`, `company_website`, `company_phone`, `company_address`, `recruit_title`, `recruit_url`, `recruit_desc`, `favicon`, `logo`, `sla_mode`, `require_job_code`, `evaluation_method`, `auto_create_from_email`, `min_delete_permission`, `min_export_permission`, `enable_captcha`, `email_interview_invitation`, `email_interview_update`, `email_interview_cancel`, `email_interview_bulk`, `interview_cv_display`, `onboard_integration_permission`, `rejection_reason_mandatory`, `auto_close_expired`, `auto_hide_expired`, `email_before_expiry`) VALUES (\'1\', \'CTY AHT TECH JSC\', \'https://www.arrowhitech.com\', \'(024)32025289\', \'Tầng 8, Mitec Tower, Đường Bình Nghệ..\', \'AHT TECH JSC - Tuyển dụng\', \'https://aht.talent.vn\', \'tuyển dụng, AHT TECH JSC, hiring, talent, vn, candidate, ứng viên, hồ sơ, nộp đơn s\', \'/public/uploads/hrm/favicon_1778050910.png\', \'/public/uploads/hrm/logo_1778050328.png\', \'Dạ tiếng...\', \'1\', \'general\', \'0\', \'admin\', \'admin\', \'0\', \'0\', \'0\', \'0\', \'0\', \'restricted\', \'manager\', \'1\', \'0\', \'0\', \'0\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_company_settings` (`id`, `company_name`, `company_website`, `company_phone`, `company_address`, `recruit_title`, `recruit_url`, `recruit_desc`, `favicon`, `logo`, `sla_mode`, `require_job_code`, `evaluation_method`, `auto_create_from_email`, `min_delete_permission`, `min_export_permission`, `enable_captcha`, `email_interview_invitation`, `email_interview_update`, `email_interview_cancel`, `email_interview_bulk`, `interview_cv_display`, `onboard_integration_permission`, `rejection_reason_mandatory`, `auto_close_expired`, `auto_hide_expired`, `email_before_expiry`) VALUES (\&#039;1\&#039;, \&#039;CTY AHT TECH JSC\&#039;, \&#039;https://www.arrowhitech.com\&#039;, \&#039;(024)32025289\&#039;, \&#039;Tầng 8, Mitec Tower, Đường Bình Nghệ..\&#039;, \&#039;AHT TECH JSC - Tuyển dụng\&#039;, \&#039;https://aht.talent.vn\&#039;, \&#039;tuyển dụng, AHT TECH JSC, hiring, talent, vn, candidate, ứng viên, hồ sơ, nộp đơn s\&#039;, \&#039;/public/uploads/hrm/favicon_1778050910.png\&#039;, \&#039;/public/uploads/hrm/logo_1778050328.png\&#039;, \&#039;Dạ tiếng...\&#039;, \&#039;1\&#039;, \&#039;general\&#039;, \&#039;0\&#039;, \&#039;admin\&#039;, \&#039;admin\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;restricted\&#039;, \&#039;manager\&#039;, \&#039;1\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;0\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `manager` varchar(255) DEFAULT NULL,
  `creators` text,
  `followers` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sort_order` int(11) DEFAULT \'0\',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `manager` varchar(255) DEFAULT NULL,
  `creators` text,
  `followers` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sort_order` int(11) DEFAULT \&#039;0\&#039;,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_departments`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_departments`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\'1\', \'Sales/Marketings\', \'Sales/Marketings\', \'\', \'\', \'\', \'2026-05-06 13:34:40\', \'0\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\&#039;1\&#039;, \&#039;Sales/Marketings\&#039;, \&#039;Sales/Marketings\&#039;, \&#039;\&#039;, \&#039;\&#039;, \&#039;\&#039;, \&#039;2026-05-06 13:34:40\&#039;, \&#039;0\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\'2\', \'Backoffice\', \'\', NULL, NULL, NULL, \'2026-05-06 13:34:40\', \'7\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\&#039;2\&#039;, \&#039;Backoffice\&#039;, \&#039;\&#039;, NULL, NULL, NULL, \&#039;2026-05-06 13:34:40\&#039;, \&#039;7\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\'3\', \'AHT Thái Nguyên\', \'Số 259 Quang Trung, Phường Tân Thịnh, Thái Nguyên\', NULL, NULL, NULL, \'2026-05-06 13:34:40\', \'6\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\&#039;3\&#039;, \&#039;AHT Thái Nguyên\&#039;, \&#039;Số 259 Quang Trung, Phường Tân Thịnh, Thái Nguyên\&#039;, NULL, NULL, NULL, \&#039;2026-05-06 13:34:40\&#039;, \&#039;6\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\'4\', \'AHT Phú Thọ\', \'Số 18 Ngõ 11, Đường Nguyễn Du, Phường Nông Trang, TP Việt Trì - Phú Thọ\', NULL, NULL, NULL, \'2026-05-06 13:34:40\', \'5\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\&#039;4\&#039;, \&#039;AHT Phú Thọ\&#039;, \&#039;Số 18 Ngõ 11, Đường Nguyễn Du, Phường Nông Trang, TP Việt Trì - Phú Thọ\&#039;, NULL, NULL, NULL, \&#039;2026-05-06 13:34:40\&#039;, \&#039;5\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\'5\', \'IT\', \'IT\', NULL, NULL, NULL, \'2026-05-06 13:34:40\', \'4\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\&#039;5\&#039;, \&#039;IT\&#039;, \&#039;IT\&#039;, NULL, NULL, NULL, \&#039;2026-05-06 13:34:40\&#039;, \&#039;4\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\'6\', \'BFSI\', \'\', NULL, NULL, NULL, \'2026-05-06 13:34:40\', \'3\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\&#039;6\&#039;, \&#039;BFSI\&#039;, \&#039;\&#039;, NULL, NULL, NULL, \&#039;2026-05-06 13:34:40\&#039;, \&#039;3\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\'7\', \'Remote/Hybrid\', \'Remote/Hybrid\', NULL, NULL, NULL, \'2026-05-06 13:34:40\', \'2\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\&#039;7\&#039;, \&#039;Remote/Hybrid\&#039;, \&#039;Remote/Hybrid\&#039;, NULL, NULL, NULL, \&#039;2026-05-06 13:34:40\&#039;, \&#039;2\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\'8\', \'Akdemy\', \'Akdemy\', NULL, NULL, NULL, \'2026-05-06 13:34:40\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_departments` (`id`, `name`, `description`, `manager`, `creators`, `followers`, `created_at`, `sort_order`) VALUES (\&#039;8\&#039;, \&#039;Akdemy\&#039;, \&#039;Akdemy\&#039;, NULL, NULL, NULL, \&#039;2026-05-06 13:34:40\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email_subject` varchar(500) DEFAULT NULL,
  `email_body` longtext,
  `is_active` tinyint(1) DEFAULT \'1\',
  `is_favorite` tinyint(1) DEFAULT \'0\',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email_subject` varchar(500) DEFAULT NULL,
  `email_body` longtext,
  `is_active` tinyint(1) DEFAULT \&#039;1\&#039;,
  `is_favorite` tinyint(1) DEFAULT \&#039;0\&#039;,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_evaluation_criteria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) DEFAULT NULL,
  `criterion_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) DEFAULT \'0\',
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `hrm_evaluation_criteria_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `hrm_evaluation_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_evaluation_criteria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) DEFAULT NULL,
  `criterion_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) DEFAULT \&#039;0\&#039;,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `hrm_evaluation_criteria_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `hrm_evaluation_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_evaluation_criteria`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_evaluation_criteria`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'1\', \'1\', \'Có bằng cấp chứng chỉ liên quan\', \'0\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;1\&#039;, \&#039;1\&#039;, \&#039;Có bằng cấp chứng chỉ liên quan\&#039;, \&#039;0\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'2\', \'1\', \'Khả năng thích nghi với môi trường và văn hóa công ty\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;2\&#039;, \&#039;1\&#039;, \&#039;Khả năng thích nghi với môi trường và văn hóa công ty\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'3\', \'1\', \'Thái độ\', \'2\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;3\&#039;, \&#039;1\&#039;, \&#039;Thái độ\&#039;, \&#039;2\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'4\', \'1\', \'Tinh thần cầu tiến và sẵn sàng học hỏi\', \'3\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;4\&#039;, \&#039;1\&#039;, \&#039;Tinh thần cầu tiến và sẵn sàng học hỏi\&#039;, \&#039;3\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'5\', \'2\', \'Android (Kotlin/ Java)\', \'0\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;5\&#039;, \&#039;2\&#039;, \&#039;Android (Kotlin/ Java)\&#039;, \&#039;0\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'6\', \'2\', \'ASP.Net\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;6\&#039;, \&#039;2\&#039;, \&#039;ASP.Net\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'7\', \'2\', \'Automation-Testing\', \'2\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;7\&#039;, \&#039;2\&#039;, \&#039;Automation-Testing\&#039;, \&#039;2\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'8\', \'2\', \'Business Analyst (BA)\', \'3\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;8\&#039;, \&#039;2\&#039;, \&#039;Business Analyst (BA)\&#039;, \&#039;3\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'9\', \'2\', \'Có cách tiếp cận ứng viên linh hoạt/sáng tạo\', \'4\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;9\&#039;, \&#039;2\&#039;, \&#039;Có cách tiếp cận ứng viên linh hoạt/sáng tạo\&#039;, \&#039;4\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'10\', \'2\', \'Có kinh nghiệm tuyển non-IT\', \'5\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;10\&#039;, \&#039;2\&#039;, \&#039;Có kinh nghiệm tuyển non-IT\&#039;, \&#039;5\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'11\', \'2\', \'Flutter\', \'6\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;11\&#039;, \&#039;2\&#039;, \&#039;Flutter\&#039;, \&#039;6\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'12\', \'2\', \'HTML-CSS\', \'7\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;12\&#039;, \&#039;2\&#039;, \&#039;HTML-CSS\&#039;, \&#039;7\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'13\', \'2\', \'iOS (Object-C/Swift)\', \'8\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;13\&#039;, \&#039;2\&#039;, \&#039;iOS (Object-C/Swift)\&#039;, \&#039;8\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'14\', \'2\', \'Java-web\', \'9\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;14\&#039;, \&#039;2\&#039;, \&#039;Java-web\&#039;, \&#039;9\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'15\', \'2\', \'Khả năng chuyên môn nghiệp vụ về Pháp chế\', \'10\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;15\&#039;, \&#039;2\&#039;, \&#039;Khả năng chuyên môn nghiệp vụ về Pháp chế\&#039;, \&#039;10\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'16\', \'2\', \'Kiến thức kỹ thuật liên quan đến dự án\', \'11\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;16\&#039;, \&#039;2\&#039;, \&#039;Kiến thức kỹ thuật liên quan đến dự án\&#039;, \&#039;11\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'17\', \'2\', \'Kinh nghiệm PM các dự án Outsourcing\', \'12\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;17\&#039;, \&#039;2\&#039;, \&#039;Kinh nghiệm PM các dự án Outsourcing\&#039;, \&#039;12\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'18\', \'2\', \'Kinh nghiệm Sales IT phù hợp\', \'13\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;18\&#039;, \&#039;2\&#039;, \&#039;Kinh nghiệm Sales IT phù hợp\&#039;, \&#039;13\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'19\', \'2\', \'Kinh nghiệm sử dụng các tool Quản lý dự án (Jira, Asana, ...)\', \'14\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;19\&#039;, \&#039;2\&#039;, \&#039;Kinh nghiệm sử dụng các tool Quản lý dự án (Jira, Asana, ...)\&#039;, \&#039;14\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'20\', \'2\', \'Kinh nghiệm với các Recruitment tool/System\', \'15\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;20\&#039;, \&#039;2\&#039;, \&#039;Kinh nghiệm với các Recruitment tool/System\&#039;, \&#039;15\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'21\', \'2\', \'Kỹ năng chuyên môn về IT Helpdesk/IT Support\', \'16\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;21\&#039;, \&#039;2\&#039;, \&#039;Kỹ năng chuyên môn về IT Helpdesk/IT Support\&#039;, \&#039;16\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'22\', \'2\', \'Magento\', \'17\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;22\&#039;, \&#039;2\&#039;, \&#039;Magento\&#039;, \&#039;17\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'23\', \'2\', \'Manual-Testing\', \'18\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;23\&#039;, \&#039;2\&#039;, \&#039;Manual-Testing\&#039;, \&#039;18\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'24\', \'2\', \'Microservices\', \'19\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;24\&#039;, \&#039;2\&#039;, \&#039;Microservices\&#039;, \&#039;19\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'25\', \'2\', \'NodeJS\', \'20\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;25\&#039;, \&#039;2\&#039;, \&#039;NodeJS\&#039;, \&#039;20\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'26\', \'2\', \'Odoo\', \'21\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;26\&#039;, \&#039;2\&#039;, \&#039;Odoo\&#039;, \&#039;21\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'27\', \'2\', \'PHP\', \'22\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;27\&#039;, \&#039;2\&#039;, \&#039;PHP\&#039;, \&#039;22\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'28\', \'2\', \'PHP - Framework\', \'23\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;28\&#039;, \&#039;2\&#039;, \&#039;PHP - Framework\&#039;, \&#039;23\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'29\', \'2\', \'Python\', \'24\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;29\&#039;, \&#039;2\&#039;, \&#039;Python\&#039;, \&#039;24\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'30\', \'2\', \'ReactJS/Angular/Vuejs\', \'25\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;30\&#039;, \&#039;2\&#039;, \&#039;ReactJS/Angular/Vuejs\&#039;, \&#039;25\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'31\', \'2\', \'React Native\', \'26\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;31\&#039;, \&#039;2\&#039;, \&#039;React Native\&#039;, \&#039;26\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'32\', \'2\', \'Salesforce\', \'27\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;32\&#039;, \&#039;2\&#039;, \&#039;Salesforce\&#039;, \&#039;27\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'33\', \'2\', \'Shopify\', \'28\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;33\&#039;, \&#039;2\&#039;, \&#039;Shopify\&#039;, \&#039;28\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'34\', \'2\', \'Software Architect (SA)\', \'29\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;34\&#039;, \&#039;2\&#039;, \&#039;Software Architect (SA)\&#039;, \&#039;29\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'35\', \'2\', \'Thực hiện quy trình tuyển dụng từ đầu tới cuối\', \'30\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;35\&#039;, \&#039;2\&#039;, \&#039;Thực hiện quy trình tuyển dụng từ đầu tới cuối\&#039;, \&#039;30\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'36\', \'2\', \'UI/UX\', \'31\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;36\&#039;, \&#039;2\&#039;, \&#039;UI/UX\&#039;, \&#039;31\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'37\', \'2\', \'Wordpress\', \'32\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;37\&#039;, \&#039;2\&#039;, \&#039;Wordpress\&#039;, \&#039;32\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'38\', \'3\', \'Kỹ năng giao tiếp\', \'0\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;38\&#039;, \&#039;3\&#039;, \&#039;Kỹ năng giao tiếp\&#039;, \&#039;0\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'39\', \'3\', \'Kỹ năng làm việc nhóm\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;39\&#039;, \&#039;3\&#039;, \&#039;Kỹ năng làm việc nhóm\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'40\', \'3\', \'Kỹ năng quản lý\', \'2\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;40\&#039;, \&#039;3\&#039;, \&#039;Kỹ năng quản lý\&#039;, \&#039;2\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'41\', \'3\', \'Network trong ngành IT\', \'3\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;41\&#039;, \&#039;3\&#039;, \&#039;Network trong ngành IT\&#039;, \&#039;3\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'42\', \'3\', \'Ngoại ngữ (Tiếng Anh, Tiếng Nhật)\', \'4\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;42\&#039;, \&#039;3\&#039;, \&#039;Ngoại ngữ (Tiếng Anh, Tiếng Nhật)\&#039;, \&#039;4\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'43\', \'4\', \'Kinh nghiệm tuyển dụng các vị trí tương đương\', \'0\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;43\&#039;, \&#039;4\&#039;, \&#039;Kinh nghiệm tuyển dụng các vị trí tương đương\&#039;, \&#039;0\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\'44\', \'4\', \'Tư duy và kinh nghiệm về Process\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_criteria` (`id`, `group_id`, `criterion_text`, `sort_order`) VALUES (\&#039;44\&#039;, \&#039;4\&#039;, \&#039;Tư duy và kinh nghiệm về Process\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_evaluation_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) DEFAULT \'0\',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_evaluation_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) DEFAULT \&#039;0\&#039;,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_evaluation_groups`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_evaluation_groups`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_groups` (`id`, `name`, `sort_order`) VALUES (\'1\', \'Khác\', \'0\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_groups` (`id`, `name`, `sort_order`) VALUES (\&#039;1\&#039;, \&#039;Khác\&#039;, \&#039;0\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_groups` (`id`, `name`, `sort_order`) VALUES (\'2\', \'Kỹ năng chuyên môn\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_groups` (`id`, `name`, `sort_order`) VALUES (\&#039;2\&#039;, \&#039;Kỹ năng chuyên môn\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_groups` (`id`, `name`, `sort_order`) VALUES (\'3\', \'Kỹ năng mềm\', \'2\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_groups` (`id`, `name`, `sort_order`) VALUES (\&#039;3\&#039;, \&#039;Kỹ năng mềm\&#039;, \&#039;2\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_evaluation_groups` (`id`, `name`, `sort_order`) VALUES (\'4\', \'Chưa phân loại\', \'3\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_evaluation_groups` (`id`, `name`, `sort_order`) VALUES (\&#039;4\&#039;, \&#039;Chưa phân loại\&#039;, \&#039;3\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_hiring_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) DEFAULT \'0\',
  `email_count` int(11) DEFAULT \'0\',
  `duration` decimal(10,2) DEFAULT \'0.00\',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_hiring_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) DEFAULT \&#039;0\&#039;,
  `email_count` int(11) DEFAULT \&#039;0\&#039;,
  `duration` decimal(10,2) DEFAULT \&#039;0.00\&#039;,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_hiring_steps`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_hiring_steps`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_hiring_steps` (`id`, `name`, `code`, `sort_order`, `email_count`, `duration`, `created_at`) VALUES (\'1\', \'Nhận hồ sơ\', \'nhan_ho_so\', \'0\', \'0\', \'0.00\', \'2026-05-06 15:58:41\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_hiring_steps` (`id`, `name`, `code`, `sort_order`, `email_count`, `duration`, `created_at`) VALUES (\&#039;1\&#039;, \&#039;Nhận hồ sơ\&#039;, \&#039;nhan_ho_so\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;0.00\&#039;, \&#039;2026-05-06 15:58:41\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_hiring_steps` (`id`, `name`, `code`, `sort_order`, `email_count`, `duration`, `created_at`) VALUES (\'2\', \'Phỏng vấn\', \'phong_van\', \'0\', \'0\', \'0.00\', \'2026-05-06 15:59:04\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_hiring_steps` (`id`, `name`, `code`, `sort_order`, `email_count`, `duration`, `created_at`) VALUES (\&#039;2\&#039;, \&#039;Phỏng vấn\&#039;, \&#039;phong_van\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;0.00\&#039;, \&#039;2026-05-06 15:59:04\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_interview_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `interview_type` varchar(100) DEFAULT \'onsite\',
  `participants` text,
  `location` text,
  `email_subject` varchar(500) DEFAULT NULL,
  `email_body` longtext,
  `questions` longtext,
  `is_active` tinyint(1) DEFAULT \'1\',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_interview_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `interview_type` varchar(100) DEFAULT \&#039;onsite\&#039;,
  `participants` text,
  `location` text,
  `email_subject` varchar(500) DEFAULT NULL,
  `email_body` longtext,
  `questions` longtext,
  `is_active` tinyint(1) DEFAULT \&#039;1\&#039;,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_interview_templates`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_interview_templates`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_interview_templates` (`id`, `name`, `interview_type`, `participants`, `location`, `email_subject`, `email_body`, `questions`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES (\'1\', \'OS MBFS - MẪU THƯ MỜI PHỎNG VẤN\', \'onsite\', \'3,11\', \'Tầng 9, Tòa nhà 5 Điện Biên Phủ, Ba Đình, Hà Nội\', \'AHT TECH - THƯ MỜI PHỎNG VẤN VỊ TRÍ {job} - VÒNG PHỎNG VẤN KHÁCH HÀNG\', \'<p>Dear bạn<strong> <em>{fullname}</em></strong>,</p>\n<p>C&ocirc;ng ty mời bạn l&uacute;c <strong><em>{time}</em>,&nbsp;</strong>tham dự phỏng vấn vị tr&iacute;<strong> <em>{job}</em></strong><strong>&nbsp;</strong>tại văn ph&ograve;ng kh&aacute;ch h&agrave;ng.&nbsp;Khi đi bạn c&oacute; thể mang theo sản phẩm đ&atilde; l&agrave;m (nếu c&oacute;) để buổi phỏng vấn đạt được kết quả tốt nhất!</p>\n<p>Bạn vui l&ograve;ng phản hồi lại Email n&agrave;y sau khi nhận được để Ph&ograve;ng Nh&acirc;n sự bố tr&iacute; tiếp đ&oacute;n.</p>\n<ul>\n<li><strong>Th&ocirc;ng tin li&ecirc;n hệ:</strong>&nbsp;\n<ul>\n<li><span class=\"ui-provider a b c d e f g h i j k l m n o p q r s t u v w x y z ab ac ae af ag ah ai aj ak\" style=\"color: #000000;\">Ms. Tr&agrave; My - 0327648259 (Account Manager - đ&oacute;n tại văn ph&ograve;ng kh&aacute;ch h&agrave;ng)</span></li>\n<li><span class=\"ui-provider a b c d e f g h i j k l m n o p q r s t u v w x y z ab ac ae af ag ah ai aj ak\" style=\"color: #000000;\">Ms. V&acirc;n T&igrave;nh - 0912212746 (HR)</span></li>\n</ul>\n</li>\n<li><strong>Địa chỉ văn ph&ograve;ng kh&aacute;ch h&agrave;ng: Tầng 9, T&ograve;a nh&agrave; 5 Điện Bi&ecirc;n Phủ, Ba Đ&igrave;nh, H&agrave; Nội&nbsp;</strong>(<a href=\"https://share.google/H8dMFQCSd62BXuBQS\">Google Maps</a>)</li>\n</ul>\n<p><em>(Hướng dẫn gửi xe: Gửi xe dưới hầm của t&ograve;a nh&agrave;)</em></p>\n<p>Mọi thắc mắc vui l&ograve;ng li&ecirc;n hệ lại Bộ phận Nh&acirc;n sự để được hỗ trợ.</p>\', \'\', \'1\', \'1\', \'2026-05-07 10:46:43\', \'2026-05-07 10:52:15\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_interview_templates` (`id`, `name`, `interview_type`, `participants`, `location`, `email_subject`, `email_body`, `questions`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES (\&#039;1\&#039;, \&#039;OS MBFS - MẪU THƯ MỜI PHỎNG VẤN\&#039;, \&#039;onsite\&#039;, \&#039;3,11\&#039;, \&#039;Tầng 9, Tòa nhà 5 Điện Biên Phủ, Ba Đình, Hà Nội\&#039;, \&#039;AHT TECH - THƯ MỜI PHỎNG VẤN VỊ TRÍ {job} - VÒNG PHỎNG VẤN KHÁCH HÀNG\&#039;, \&#039;&lt;p&gt;Dear bạn&lt;strong&gt; &lt;em&gt;{fullname}&lt;/em&gt;&lt;/strong&gt;,&lt;/p&gt;\n&lt;p&gt;C&amp;ocirc;ng ty mời bạn l&amp;uacute;c &lt;strong&gt;&lt;em&gt;{time}&lt;/em&gt;,&amp;nbsp;&lt;/strong&gt;tham dự phỏng vấn vị tr&amp;iacute;&lt;strong&gt; &lt;em&gt;{job}&lt;/em&gt;&lt;/strong&gt;&lt;strong&gt;&amp;nbsp;&lt;/strong&gt;tại văn ph&amp;ograve;ng kh&amp;aacute;ch h&amp;agrave;ng.&amp;nbsp;Khi đi bạn c&amp;oacute; thể mang theo sản phẩm đ&amp;atilde; l&amp;agrave;m (nếu c&amp;oacute;) để buổi phỏng vấn đạt được kết quả tốt nhất!&lt;/p&gt;\n&lt;p&gt;Bạn vui l&amp;ograve;ng phản hồi lại Email n&amp;agrave;y sau khi nhận được để Ph&amp;ograve;ng Nh&amp;acirc;n sự bố tr&amp;iacute; tiếp đ&amp;oacute;n.&lt;/p&gt;\n&lt;ul&gt;\n&lt;li&gt;&lt;strong&gt;Th&amp;ocirc;ng tin li&amp;ecirc;n hệ:&lt;/strong&gt;&amp;nbsp;\n&lt;ul&gt;\n&lt;li&gt;&lt;span class=\&quot;ui-provider a b c d e f g h i j k l m n o p q r s t u v w x y z ab ac ae af ag ah ai aj ak\&quot; style=\&quot;color: #000000;\&quot;&gt;Ms. Tr&amp;agrave; My - 0327648259 (Account Manager - đ&amp;oacute;n tại văn ph&amp;ograve;ng kh&amp;aacute;ch h&amp;agrave;ng)&lt;/span&gt;&lt;/li&gt;\n&lt;li&gt;&lt;span class=\&quot;ui-provider a b c d e f g h i j k l m n o p q r s t u v w x y z ab ac ae af ag ah ai aj ak\&quot; style=\&quot;color: #000000;\&quot;&gt;Ms. V&amp;acirc;n T&amp;igrave;nh - 0912212746 (HR)&lt;/span&gt;&lt;/li&gt;\n&lt;/ul&gt;\n&lt;/li&gt;\n&lt;li&gt;&lt;strong&gt;Địa chỉ văn ph&amp;ograve;ng kh&amp;aacute;ch h&amp;agrave;ng: Tầng 9, T&amp;ograve;a nh&amp;agrave; 5 Điện Bi&amp;ecirc;n Phủ, Ba Đ&amp;igrave;nh, H&amp;agrave; Nội&amp;nbsp;&lt;/strong&gt;(&lt;a href=\&quot;https://share.google/H8dMFQCSd62BXuBQS\&quot;&gt;Google Maps&lt;/a&gt;)&lt;/li&gt;\n&lt;/ul&gt;\n&lt;p&gt;&lt;em&gt;(Hướng dẫn gửi xe: Gửi xe dưới hầm của t&amp;ograve;a nh&amp;agrave;)&lt;/em&gt;&lt;/p&gt;\n&lt;p&gt;Mọi thắc mắc vui l&amp;ograve;ng li&amp;ecirc;n hệ lại Bộ phận Nh&amp;acirc;n sự để được hỗ trợ.&lt;/p&gt;\&#039;, \&#039;\&#039;, \&#039;1\&#039;, \&#039;1\&#039;, \&#039;2026-05-07 10:46:43\&#039;, \&#039;2026-05-07 10:52:15\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_job_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `office` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salary_from` decimal(15,2) DEFAULT NULL,
  `salary_to` decimal(15,2) DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT \'VND\',
  `show_salary` tinyint(1) DEFAULT \'1\',
  `quantity` int(11) DEFAULT NULL,
  `job_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `job_description` text COLLATE utf8mb4_unicode_ci,
  `talent_pool_id` int(11) DEFAULT NULL,
  `managers` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `completion_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT \'draft\',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_job_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `office` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salary_from` decimal(15,2) DEFAULT NULL,
  `salary_to` decimal(15,2) DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT \&#039;VND\&#039;,
  `show_salary` tinyint(1) DEFAULT \&#039;1\&#039;,
  `quantity` int(11) DEFAULT NULL,
  `job_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `job_description` text COLLATE utf8mb4_unicode_ci,
  `talent_pool_id` int(11) DEFAULT NULL,
  `managers` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `completion_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT \&#039;draft\&#039;,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_job_posts`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_job_posts`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_job_posts` (`id`, `title`, `job_code`, `template_id`, `department_id`, `office`, `salary_from`, `salary_to`, `currency`, `show_salary`, `quantity`, `job_type`, `deadline`, `job_description`, `talent_pool_id`, `managers`, `notes`, `completion_time`, `city`, `district`, `address`, `postal_code`, `status`, `created_at`) VALUES (\'1\', \'sdfsdsds\', \'\', \'0\', \'0\', \'\', \'0.00\', \'0.00\', \'VND\', \'1\', \'0\', \'Nhân viên toàn thời gian\', NULL, \'\', \'0\', \'\', \'\', \'\', \'\', \'\', \'\', \'\', \'draft\', \'2026-05-06 18:02:11\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_job_posts` (`id`, `title`, `job_code`, `template_id`, `department_id`, `office`, `salary_from`, `salary_to`, `currency`, `show_salary`, `quantity`, `job_type`, `deadline`, `job_description`, `talent_pool_id`, `managers`, `notes`, `completion_time`, `city`, `district`, `address`, `postal_code`, `status`, `created_at`) VALUES (\&#039;1\&#039;, \&#039;sdfsdsds\&#039;, \&#039;\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;\&#039;, \&#039;0.00\&#039;, \&#039;0.00\&#039;, \&#039;VND\&#039;, \&#039;1\&#039;, \&#039;0\&#039;, \&#039;Nhân viên toàn thời gian\&#039;, NULL, \&#039;\&#039;, \&#039;0\&#039;, \&#039;\&#039;, \&#039;\&#039;, \&#039;\&#039;, \&#039;\&#039;, \&#039;\&#039;, \&#039;\&#039;, \&#039;\&#039;, \&#039;draft\&#039;, \&#039;2026-05-06 18:02:11\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_offices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sort_order` int(11) DEFAULT \'0\',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_offices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sort_order` int(11) DEFAULT \&#039;0\&#039;,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_offices`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_offices`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'1\', \'AHT TECH HEAD OFFICE - Tầng 8, Tòa nhà MITEC, Lô E2, KĐTM Cầu Giấy, Phường Cầu Giấy, TP Hà Nội\', \'AHT TECH HEAD OFFICE - Tầng 8, Tòa nhà MITEC, Lô E2, KĐTM Cầu Giấy, Phường Cầu Giấy, TP Hà Nội\', \'2026-05-06 13:42:49\', \'0\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;1\&#039;, \&#039;AHT TECH HEAD OFFICE - Tầng 8, Tòa nhà MITEC, Lô E2, KĐTM Cầu Giấy, Phường Cầu Giấy, TP Hà Nội\&#039;, \&#039;AHT TECH HEAD OFFICE - Tầng 8, Tòa nhà MITEC, Lô E2, KĐTM Cầu Giấy, Phường Cầu Giấy, TP Hà Nội\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;0\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'2\', \'AHT TECH - Văn phòng TP. Hồ Chí Minh - Tầng 7, Tòa nhà Jea Building, 112 Lý Chính Thắng, Phường Xuân Hoà, Thành Phố Hồ Chí Minh\', \'AHT TECH - Văn phòng TP. Hồ Chí Minh - Tầng 7, Tòa nhà Jea Building, 112 Lý Chính Thắng, Phường Xuân Hoà, Thành Phố Hồ Chí Minh\', \'2026-05-06 13:42:49\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;2\&#039;, \&#039;AHT TECH - Văn phòng TP. Hồ Chí Minh - Tầng 7, Tòa nhà Jea Building, 112 Lý Chính Thắng, Phường Xuân Hoà, Thành Phố Hồ Chí Minh\&#039;, \&#039;AHT TECH - Văn phòng TP. Hồ Chí Minh - Tầng 7, Tòa nhà Jea Building, 112 Lý Chính Thắng, Phường Xuân Hoà, Thành Phố Hồ Chí Minh\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'3\', \'AHT Phú Thọ - Số 18 Ngõ 11, Đường Nguyễn Du, Phường Nông Trang, TP Việt Trì - Phú Thọ\', \'Số 18 Ngõ 11, Đường Nguyễn Du, Phường Nông Trang, TP Việt Trì – Phú Thọ\', \'2026-05-06 13:42:49\', \'2\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;3\&#039;, \&#039;AHT Phú Thọ - Số 18 Ngõ 11, Đường Nguyễn Du, Phường Nông Trang, TP Việt Trì - Phú Thọ\&#039;, \&#039;Số 18 Ngõ 11, Đường Nguyễn Du, Phường Nông Trang, TP Việt Trì – Phú Thọ\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;2\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'4\', \'Văn phòng đối tác\', \'\', \'2026-05-06 13:42:49\', \'16\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;4\&#039;, \&#039;Văn phòng đối tác\&#039;, \&#039;\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;16\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'5\', \'Malaysia\', \'\', \'2026-05-06 13:42:49\', \'15\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;5\&#039;, \&#039;Malaysia\&#039;, \&#039;\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;15\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'6\', \'Remote/hybrid\', \'\', \'2026-05-06 13:42:49\', \'14\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;6\&#039;, \&#039;Remote/hybrid\&#039;, \&#039;\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;14\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'7\', \'Văn phòng Đối tác – Lê Ngọc Hân, Hai Bà Trưng\', \'Văn phòng Đối tác – Lê Ngọc Hân, Hai Bà Trưng\', \'2026-05-06 13:42:49\', \'13\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;7\&#039;, \&#039;Văn phòng Đối tác – Lê Ngọc Hân, Hai Bà Trưng\&#039;, \&#039;Văn phòng Đối tác – Lê Ngọc Hân, Hai Bà Trưng\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;13\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'8\', \'Văn phòng Đối tác – Trần Quang Khải, Hoàn Kiếm\', \'Văn phòng Đối tác – Trần Quang Khải, Hoàn Kiếm\', \'2026-05-06 13:42:49\', \'12\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;8\&#039;, \&#039;Văn phòng Đối tác – Trần Quang Khải, Hoàn Kiếm\&#039;, \&#039;Văn phòng Đối tác – Trần Quang Khải, Hoàn Kiếm\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;12\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'9\', \'Văn phòng Đối tác – Trần Hưng Đạo, Hoàn Kiếm\', \'Văn phòng Đối tác – Trần Hưng Đạo, Hoàn Kiếm\', \'2026-05-06 13:42:49\', \'11\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;9\&#039;, \&#039;Văn phòng Đối tác – Trần Hưng Đạo, Hoàn Kiếm\&#039;, \&#039;Văn phòng Đối tác – Trần Hưng Đạo, Hoàn Kiếm\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;11\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'10\', \'Văn phòng Đối tác – Mỹ Đình, Phường Yên Hòa, Hà Nội\', \'Văn phòng Đối tác – Mỹ Đình, Phường Yên Hòa, Hà Nội\', \'2026-05-06 13:42:49\', \'10\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;10\&#039;, \&#039;Văn phòng Đối tác – Mỹ Đình, Phường Yên Hòa, Hà Nội\&#039;, \&#039;Văn phòng Đối tác – Mỹ Đình, Phường Yên Hòa, Hà Nội\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;10\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'11\', \'Văn phòng Đối tác – Nguyễn Tuân, Thanh Xuân\', \'Văn phòng Đối tác – Nguyễn Tuân, Thanh Xuân\', \'2026-05-06 13:42:49\', \'9\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;11\&#039;, \&#039;Văn phòng Đối tác – Nguyễn Tuân, Thanh Xuân\&#039;, \&#039;Văn phòng Đối tác – Nguyễn Tuân, Thanh Xuân\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;9\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'12\', \'Văn phòng Đối tác – Xuân Thủy, Cầu Giấy\', \'Văn phòng Đối tác – Xuân Thủy, Cầu Giấy\', \'2026-05-06 13:42:49\', \'8\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;12\&#039;, \&#039;Văn phòng Đối tác – Xuân Thủy, Cầu Giấy\&#039;, \&#039;Văn phòng Đối tác – Xuân Thủy, Cầu Giấy\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;8\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'13\', \'Văn phòng Đối tác – Nguyễn Chí Thanh, Hà Nội\', \'Văn phòng Đối tác – Nguyễn Chí Thanh, Hà Nội\', \'2026-05-06 13:42:49\', \'7\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;13\&#039;, \&#039;Văn phòng Đối tác – Nguyễn Chí Thanh, Hà Nội\&#039;, \&#039;Văn phòng Đối tác – Nguyễn Chí Thanh, Hà Nội\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;7\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'14\', \'Văn phòng Đối tác – Láng Hạ, Đống Đa\', \'Văn phòng Đối tác – Láng Hạ, Đống Đa\', \'2026-05-06 13:42:49\', \'6\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;14\&#039;, \&#039;Văn phòng Đối tác – Láng Hạ, Đống Đa\&#039;, \&#039;Văn phòng Đối tác – Láng Hạ, Đống Đa\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;6\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'15\', \'Văn phòng Đối tác – Huỳnh Thúc Kháng, Hà Nội\', \'Văn phòng Đối tác – Huỳnh Thúc Kháng, Hà Nội\', \'2026-05-06 13:42:49\', \'5\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;15\&#039;, \&#039;Văn phòng Đối tác – Huỳnh Thúc Kháng, Hà Nội\&#039;, \&#039;Văn phòng Đối tác – Huỳnh Thúc Kháng, Hà Nội\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;5\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'16\', \'Văn phòng Đối tác – Định Vương Hậu, Hà Nội\', \'Văn phòng Đối tác – Định Vương Hậu, Hà Nội\', \'2026-05-06 13:42:49\', \'4\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;16\&#039;, \&#039;Văn phòng Đối tác – Định Vương Hậu, Hà Nội\&#039;, \&#039;Văn phòng Đối tác – Định Vương Hậu, Hà Nội\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;4\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\'17\', \'Văn phòng Đối tác – Nguyễn Phong Sắc, Hà Nội\', \'Văn phòng Đối tác – Nguyễn Phong Sắc, Hà Nội\', \'2026-05-06 13:42:49\', \'3\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`) VALUES (\&#039;17\&#039;, \&#039;Văn phòng Đối tác – Nguyễn Phong Sắc, Hà Nội\&#039;, \&#039;Văn phòng Đối tác – Nguyễn Phong Sắc, Hà Nội\&#039;, \&#039;2026-05-06 13:42:49\&#039;, \&#039;3\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`role`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`role`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_permissions`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_permissions`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_permissions` (`id`, `user_id`, `role`, `created_at`) VALUES (\'1\', \'20\', \'manager\', \'2026-05-06 15:38:16\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_permissions` (`id`, `user_id`, `role`, `created_at`) VALUES (\&#039;1\&#039;, \&#039;20\&#039;, \&#039;manager\&#039;, \&#039;2026-05-06 15:38:16\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_permissions` (`id`, `user_id`, `role`, `created_at`) VALUES (\'2\', \'11\', \'manager\', \'2026-05-06 15:38:23\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_permissions` (`id`, `user_id`, `role`, `created_at`) VALUES (\&#039;2\&#039;, \&#039;11\&#039;, \&#039;manager\&#039;, \&#039;2026-05-06 15:38:23\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_permissions` (`id`, `user_id`, `role`, `created_at`) VALUES (\'3\', \'3\', \'executive\', \'2026-05-06 15:38:32\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_permissions` (`id`, `user_id`, `role`, `created_at`) VALUES (\&#039;3\&#039;, \&#039;3\&#039;, \&#039;executive\&#039;, \&#039;2026-05-06 15:38:32\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_permissions` (`id`, `user_id`, `role`, `created_at`) VALUES (\'4\', \'3\', \'manager\', \'2026-05-06 16:25:11\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_permissions` (`id`, `user_id`, `role`, `created_at`) VALUES (\&#039;4\&#039;, \&#039;3\&#039;, \&#039;manager\&#039;, \&#039;2026-05-06 16:25:11\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_proposal_approvers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proposal_type` enum(\'recruitment\',\'hiring\') COLLATE utf8mb4_unicode_ci NOT NULL,
  `approver_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `block_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `sla_hours` int(11) DEFAULT \'0\',
  `sort_order` int(11) DEFAULT \'0\',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_proposal_approvers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proposal_type` enum(\&#039;recruitment\&#039;,\&#039;hiring\&#039;) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approver_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `block_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `sla_hours` int(11) DEFAULT \&#039;0\&#039;,
  `sort_order` int(11) DEFAULT \&#039;0\&#039;,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_proposal_approvers`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_proposal_approvers`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_proposal_approvers` (`id`, `proposal_type`, `approver_type`, `block_name`, `user_id`, `sla_hours`, `sort_order`, `created_at`, `metadata`) VALUES (\'1\', \'recruitment\', \'fixed\', \'\', \'3\', \'0\', \'0\', \'2026-05-06 16:29:17\', \'\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_proposal_approvers` (`id`, `proposal_type`, `approver_type`, `block_name`, `user_id`, `sla_hours`, `sort_order`, `created_at`, `metadata`) VALUES (\&#039;1\&#039;, \&#039;recruitment\&#039;, \&#039;fixed\&#039;, \&#039;\&#039;, \&#039;3\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;2026-05-06 16:29:17\&#039;, \&#039;\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_proposal_approvers` (`id`, `proposal_type`, `approver_type`, `block_name`, `user_id`, `sla_hours`, `sort_order`, `created_at`, `metadata`) VALUES (\'2\', \'recruitment\', \'dynamic\', \'BFG\', \'0\', \'0\', \'0\', \'2026-05-06 16:29:53\', \'{\"restrict\":\"1\",\"required\":\"1\"}\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_proposal_approvers` (`id`, `proposal_type`, `approver_type`, `block_name`, `user_id`, `sla_hours`, `sort_order`, `created_at`, `metadata`) VALUES (\&#039;2\&#039;, \&#039;recruitment\&#039;, \&#039;dynamic\&#039;, \&#039;BFG\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;2026-05-06 16:29:53\&#039;, \&#039;{\&quot;restrict\&quot;:\&quot;1\&quot;,\&quot;required\&quot;:\&quot;1\&quot;}\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_proposal_approvers` (`id`, `proposal_type`, `approver_type`, `block_name`, `user_id`, `sla_hours`, `sort_order`, `created_at`, `metadata`) VALUES (\'3\', \'hiring\', \'fixed\', \'\', \'3\', \'0\', \'0\', \'2026-05-06 16:31:06\', \'\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_proposal_approvers` (`id`, `proposal_type`, `approver_type`, `block_name`, `user_id`, `sla_hours`, `sort_order`, `created_at`, `metadata`) VALUES (\&#039;3\&#039;, \&#039;hiring\&#039;, \&#039;fixed\&#039;, \&#039;\&#039;, \&#039;3\&#039;, \&#039;0\&#039;, \&#039;0\&#039;, \&#039;2026-05-06 16:31:06\&#039;, \&#039;\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_proposal_followers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proposal_type` enum(\'recruitment\',\'hiring\') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_type` (`proposal_type`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_proposal_followers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proposal_type` enum(\&#039;recruitment\&#039;,\&#039;hiring\&#039;) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_type` (`proposal_type`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_proposal_followers`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_proposal_followers`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_proposal_followers` (`id`, `proposal_type`, `user_id`) VALUES (\'6\', \'recruitment\', \'11\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_proposal_followers` (`id`, `proposal_type`, `user_id`) VALUES (\&#039;6\&#039;, \&#039;recruitment\&#039;, \&#039;11\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_proposal_followers` (`id`, `proposal_type`, `user_id`) VALUES (\'1\', \'recruitment\', \'20\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_proposal_followers` (`id`, `proposal_type`, `user_id`) VALUES (\&#039;1\&#039;, \&#039;recruitment\&#039;, \&#039;20\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_proposal_followers` (`id`, `proposal_type`, `user_id`) VALUES (\'7\', \'hiring\', \'3\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_proposal_followers` (`id`, `proposal_type`, `user_id`) VALUES (\&#039;7\&#039;, \&#039;hiring\&#039;, \&#039;3\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_proposal_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proposal_type` enum(\'recruitment\',\'hiring\') COLLATE utf8mb4_unicode_ci NOT NULL,
  `approval_flow` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT \'sequential\',
  `role_priority` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT \'last\',
  `hrm_edit_after_approval` tinyint(1) DEFAULT \'0\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_type` (`proposal_type`)
) ENGINE=InnoDB AUTO_INCREMENT=435 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_proposal_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proposal_type` enum(\&#039;recruitment\&#039;,\&#039;hiring\&#039;) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approval_flow` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT \&#039;sequential\&#039;,
  `role_priority` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT \&#039;last\&#039;,
  `hrm_edit_after_approval` tinyint(1) DEFAULT \&#039;0\&#039;,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_type` (`proposal_type`)
) ENGINE=InnoDB AUTO_INCREMENT=435 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_proposal_settings`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_proposal_settings`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_proposal_settings` (`id`, `proposal_type`, `approval_flow`, `role_priority`, `hrm_edit_after_approval`) VALUES (\'1\', \'recruitment\', \'parallel\', \'first\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_proposal_settings` (`id`, `proposal_type`, `approval_flow`, `role_priority`, `hrm_edit_after_approval`) VALUES (\&#039;1\&#039;, \&#039;recruitment\&#039;, \&#039;parallel\&#039;, \&#039;first\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_proposal_settings` (`id`, `proposal_type`, `approval_flow`, `role_priority`, `hrm_edit_after_approval`) VALUES (\'2\', \'hiring\', \'sequential\', \'first\', \'0\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_proposal_settings` (`id`, `proposal_type`, `approval_flow`, `role_priority`, `hrm_edit_after_approval`) VALUES (\&#039;2\&#039;, \&#039;hiring\&#039;, \&#039;sequential\&#039;, \&#039;first\&#039;, \&#039;0\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_rejection_reasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reason_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT \'1\',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sort_order` int(11) DEFAULT \'0\',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_rejection_reasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reason_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT \&#039;1\&#039;,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sort_order` int(11) DEFAULT \&#039;0\&#039;,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_rejection_reasons`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_rejection_reasons`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\'1\', \'ĐÃ CÓ OFFER CÔNG TY KHÁC\', \'da-co-offer-cong-ty-khac\', \'1\', \'luongnt\', \'2022-11-17 10:00:00\', \'0\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\&#039;1\&#039;, \&#039;ĐÃ CÓ OFFER CÔNG TY KHÁC\&#039;, \&#039;da-co-offer-cong-ty-khac\&#039;, \&#039;1\&#039;, \&#039;luongnt\&#039;, \&#039;2022-11-17 10:00:00\&#039;, \&#039;0\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\'2\', \'TIẾNG ANH CHƯA PHÙ HỢP\', \'tieng-anh-chua-phu-hop\', \'1\', \'luongnt\', \'2022-11-17 11:00:00\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\&#039;2\&#039;, \&#039;TIẾNG ANH CHƯA PHÙ HỢP\&#039;, \&#039;tieng-anh-chua-phu-hop\&#039;, \&#039;1\&#039;, \&#039;luongnt\&#039;, \&#039;2022-11-17 11:00:00\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\'3\', \'KHÔNG ĐẠT YÊU CẦU\', \'khong-dat-yeu-cau\', \'1\', \'luongnt\', \'2022-11-16 09:00:00\', \'2\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\&#039;3\&#039;, \&#039;KHÔNG ĐẠT YÊU CẦU\&#039;, \&#039;khong-dat-yeu-cau\&#039;, \&#039;1\&#039;, \&#039;luongnt\&#039;, \&#039;2022-11-16 09:00:00\&#039;, \&#039;2\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\'4\', \'MÔI TRƯỜNG, ĐỊNH HƯỚNG KHÔNG PHÙ HỢP\', \'moi-truong-dinh-huong-khong-phu-hop\', \'1\', \'luongnt\', \'2022-11-16 14:00:00\', \'3\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\&#039;4\&#039;, \&#039;MÔI TRƯỜNG, ĐỊNH HƯỚNG KHÔNG PHÙ HỢP\&#039;, \&#039;moi-truong-dinh-huong-khong-phu-hop\&#039;, \&#039;1\&#039;, \&#039;luongnt\&#039;, \&#039;2022-11-16 14:00:00\&#039;, \&#039;3\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\'5\', \'OUT OF BUDGET\', \'out-of-budget\', \'1\', \'luongnt\', \'2022-11-16 15:00:00\', \'4\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\&#039;5\&#039;, \&#039;OUT OF BUDGET\&#039;, \&#039;out-of-budget\&#039;, \&#039;1\&#039;, \&#039;luongnt\&#039;, \&#039;2022-11-16 15:00:00\&#039;, \&#039;4\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\'6\', \'OTHER\', \'other\', \'1\', \'hệ thống\', \'2022-11-14 08:00:00\', \'5\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_rejection_reasons` (`id`, `reason_text`, `reason_code`, `is_active`, `created_by`, `created_at`, `sort_order`) VALUES (\&#039;6\&#039;, \&#039;OTHER\&#039;, \&#039;other\&#039;, \&#039;1\&#039;, \&#039;hệ thống\&#039;, \&#039;2022-11-14 08:00:00\&#039;, \&#039;5\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT \'1\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_id` (`role_id`,`permission_key`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT \&#039;1\&#039;,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_id` (`role_id`,`permission_key`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_role_permissions`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_role_permissions`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'1\', \'1\', \'candidate_view\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;1\&#039;, \&#039;1\&#039;, \&#039;candidate_view\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'2\', \'1\', \'candidate_create\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;2\&#039;, \&#039;1\&#039;, \&#039;candidate_create\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'3\', \'1\', \'candidate_edit\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;3\&#039;, \&#039;1\&#039;, \&#039;candidate_edit\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'4\', \'1\', \'candidate_delete\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;4\&#039;, \&#039;1\&#039;, \&#039;candidate_delete\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'5\', \'1\', \'candidate_export\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;5\&#039;, \&#039;1\&#039;, \&#039;candidate_export\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'6\', \'1\', \'job_view\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;6\&#039;, \&#039;1\&#039;, \&#039;job_view\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'7\', \'1\', \'job_create\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;7\&#039;, \&#039;1\&#039;, \&#039;job_create\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'8\', \'1\', \'job_edit\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;8\&#039;, \&#039;1\&#039;, \&#039;job_edit\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'9\', \'1\', \'job_delete\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;9\&#039;, \&#039;1\&#039;, \&#039;job_delete\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'10\', \'1\', \'job_publish\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;10\&#039;, \&#039;1\&#039;, \&#039;job_publish\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'11\', \'1\', \'interview_view\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;11\&#039;, \&#039;1\&#039;, \&#039;interview_view\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'12\', \'1\', \'interview_schedule\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;12\&#039;, \&#039;1\&#039;, \&#039;interview_schedule\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'13\', \'1\', \'interview_evaluate\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;13\&#039;, \&#039;1\&#039;, \&#039;interview_evaluate\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'14\', \'1\', \'report_view_general\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;14\&#039;, \&#039;1\&#039;, \&#039;report_view_general\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'15\', \'1\', \'report_view_detail\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;15\&#039;, \&#039;1\&#039;, \&#039;report_view_detail\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'16\', \'1\', \'settings_view\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;16\&#039;, \&#039;1\&#039;, \&#039;settings_view\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\'17\', \'1\', \'settings_edit\', \'1\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_role_permissions` (`id`, `role_id`, `permission_key`, `is_enabled`) VALUES (\&#039;17\&#039;, \&#039;1\&#039;, \&#039;settings_edit\&#039;, \&#039;1\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `is_system` tinyint(1) DEFAULT \'0\',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `is_system` tinyint(1) DEFAULT \&#039;0\&#039;,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_roles`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_roles`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_roles` (`id`, `name`, `description`, `is_system`, `created_at`) VALUES (\'1\', \'HR Administrator\', \'Toàn quyền quản trị hệ thống\', \'1\', \'2026-05-06 17:06:48\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_roles` (`id`, `name`, `description`, `is_system`, `created_at`) VALUES (\&#039;1\&#039;, \&#039;HR Administrator\&#039;, \&#039;Toàn quyền quản trị hệ thống\&#039;, \&#039;1\&#039;, \&#039;2026-05-06 17:06:48\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_roles` (`id`, `name`, `description`, `is_system`, `created_at`) VALUES (\'2\', \'Recruiter\', \'Chuyên viên tuyển dụng, quản lý tin và ứng viên\', \'1\', \'2026-05-06 17:06:48\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_roles` (`id`, `name`, `description`, `is_system`, `created_at`) VALUES (\&#039;2\&#039;, \&#039;Recruiter\&#039;, \&#039;Chuyên viên tuyển dụng, quản lý tin và ứng viên\&#039;, \&#039;1\&#039;, \&#039;2026-05-06 17:06:48\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_roles` (`id`, `name`, `description`, `is_system`, `created_at`) VALUES (\'3\', \'Hiring Manager\', \'Quản lý trực tiếp, phê duyệt đề xuất và đánh giá ứng viên\', \'1\', \'2026-05-06 17:06:48\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_roles` (`id`, `name`, `description`, `is_system`, `created_at`) VALUES (\&#039;3\&#039;, \&#039;Hiring Manager\&#039;, \&#039;Quản lý trực tiếp, phê duyệt đề xuất và đánh giá ứng viên\&#039;, \&#039;1\&#039;, \&#039;2026-05-06 17:06:48\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_roles` (`id`, `name`, `description`, `is_system`, `created_at`) VALUES (\'4\', \'Interviewer\', \'Người tham gia phỏng vấn và đánh giá chuyên môn\', \'1\', \'2026-05-06 17:06:48\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_roles` (`id`, `name`, `description`, `is_system`, `created_at`) VALUES (\&#039;4\&#039;, \&#039;Interviewer\&#039;, \&#039;Người tham gia phỏng vấn và đánh giá chuyên môn\&#039;, \&#039;1\&#039;, \&#039;2026-05-06 17:06:48\&#039;);</small></li>';
}
if ($conn->query('CREATE TABLE IF NOT EXISTS `hrm_talent_pools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: CREATE TABLE IF NOT EXISTS `hrm_talent_pools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;</small></li>';
}
if ($conn->query('DELETE FROM `hrm_talent_pools`;')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: DELETE FROM `hrm_talent_pools`;</small></li>';
}
if ($conn->query('INSERT INTO `hrm_talent_pools` (`id`, `name`, `description`, `created_by`, `created_at`) VALUES (\'1\', \'Engineering Pool\', \'\', \'1\', \'2026-05-07 08:31:41\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_talent_pools` (`id`, `name`, `description`, `created_by`, `created_at`) VALUES (\&#039;1\&#039;, \&#039;Engineering Pool\&#039;, \&#039;\&#039;, \&#039;1\&#039;, \&#039;2026-05-07 08:31:41\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_talent_pools` (`id`, `name`, `description`, `created_by`, `created_at`) VALUES (\'2\', \'Marketing Pool\', \'Candidates for marketing positions.\', \'1\', \'2026-05-07 08:36:27\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_talent_pools` (`id`, `name`, `description`, `created_by`, `created_at`) VALUES (\&#039;2\&#039;, \&#039;Marketing Pool\&#039;, \&#039;Candidates for marketing positions.\&#039;, \&#039;1\&#039;, \&#039;2026-05-07 08:36:27\&#039;);</small></li>';
}
if ($conn->query('INSERT INTO `hrm_talent_pools` (`id`, `name`, `description`, `created_by`, `created_at`) VALUES (\'4\', \'All Databases\', \'\', \'1\', \'2026-05-07 10:31:42\');')) {
    // echo '<li>Thành công</li>';
} else {
    echo '<li style="color:red">Lỗi: ' . $conn->error . ' <br><small>Query: INSERT INTO `hrm_talent_pools` (`id`, `name`, `description`, `created_by`, `created_at`) VALUES (\&#039;4\&#039;, \&#039;All Databases\&#039;, \&#039;\&#039;, \&#039;1\&#039;, \&#039;2026-05-07 10:31:42\&#039;);</small></li>';
}

echo '</ul><h3>✅ HOÀN TẤT IMPORT! Bạn có thể xoá file import_hrm.php này khỏi server.</h3>';
