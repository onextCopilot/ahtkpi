<?php
/**
 * Bảng quy đổi handle Base (@username) -> Tên đầy đủ.
 * Dùng khi import "Phụ trách" / "Từ chối bởi" từ Base: handle -> tên -> khớp user OS,
 * nếu không có trong OS thì lưu tên này dạng text.
 *
 * Seed từ danh bạ thành viên Base (đa số đã vô hiệu hóa). BỔ SUNG TỰ DO ở đây:
 * key = handle viết thường (không có @), value = tên đầy đủ.
 */
function hrm_base_aliases(): array
{
    return [
        // trang 1
        'trangtq1' => 'Nguyễn Thị Quỳnh Trang', 'chili' => 'Lê Hà Chi', 'tuyenkc' => 'Khúc Chí Tuyên',
        'giangnt2' => 'Nguyễn Thị Giang', 'dungtt1' => 'Trần Thị Dung Dung', 'huongntt' => 'Nguyễn Thị Thu Hương',
        'anhdtm' => 'Đỗ Thị Minh Anh', 'linhtt1' => 'Tạ Thùy Linh', 'mynh' => 'Nguyễn Hà My',
        'thaoctp' => 'Cao Thị Phương Thảo', 'hanhtnh' => 'Nguyễn Thị Hồng Hạnh', 'tinhnv' => 'Nguyễn Thị Vân Tịnh',
        'huongdt2' => 'Dương Thu Hương', 'anhlp' => 'Lương Phương Anh', 'phuongnm' => 'Nguyễn Minh Phương',
        'thuyht' => 'Hoàng Thanh Thủy', 'ducnv' => 'Nguyễn Văn Đức', 'nhatnm' => 'Nguyễn Minh Nhật',
        'uyenltt' => 'Lê Thị Thu Uyên', 'luctv' => 'Thân Văn Lực', 'tuyetnt' => 'Nguyễn Thị Tuyết',
        'anhvm' => 'Vũ Mai Anh', 'huyenbtt' => 'Bùi Thị Thanh Huyền', 'linhvt' => 'Võ Tùng Linh',
        'anhntv' => 'Nguyễn Thị Vân Anh',
        // trang 2
        'vuongnq' => 'Nguyễn Quốc Vương', 'anhvn' => 'Vũ Ngân Anh', 'hungnt1' => 'Nguyễn Trung Hưng',
        'lamnt' => 'Nguyễn Thành Lam', 'doannq' => 'Ngô Quốc Đoàn', 'anhpq1' => 'Phạm Quỳnh Anh',
        'duyenntm' => 'Nguyễn Thị Mỹ Duyên', 'anch' => 'Chu Hữu An', 'thaovn' => 'Vũ Nguyên Thảo',
        'truyentt' => 'Tạ Thị Truyền', 'loantt' => 'Trần Thị Loan', 'hieunh' => 'Nguyễn Hoàng Hiếu',
        'taipv' => 'Phạm Văn Tài', 'nghiadt' => 'Đỗ Trung Nghĩa', 'thaobt' => 'Bùi Thị Thảo',
        'thuytt1' => 'Trần Thị Thanh Thủy', 'hoangnv' => 'Nguyễn Việt Hoàng', 'nghiavt' => 'Vũ Trọng Nghĩa',
        'dunglm' => 'Lê Minh Dũng', 'kienhv' => 'Hà Văn Kiên', 'trungnn' => 'Nguyễn Ngọc Trung',
        // trang 3
        'hoangn' => 'Nguyễn Hoàng', 'quanlv' => 'Lê Văn Quân', 'minhbc' => 'Bùi Công Minh',
        'huyld' => 'Lê Đức Huy', 'binhnd' => 'Nguyễn Đức Bình', 'datpq' => 'Phạm Quốc Đạt',
        'tunglt' => 'Lục Thanh Tùng', 'minhtd' => 'Trương Đức Minh', 'vinhph' => 'Phạm Hồng Vĩnh',
        'thinhlv' => 'Lê Văn Thịnh', 'khanhdt' => 'Đào Thị Khánh', 'trungda' => 'Đàm Anh Trung',
        'namnv' => 'Nguyễn Văn Nam', 'tuanva' => 'Vũ Anh Tuấn', 'lucnt' => 'Nguyễn Tiến Lục',
        'ducdt' => 'Đỗ Trung Đức',
        // trang 4
        'nhungvt' => 'Vũ Thị Nhung', 'annn' => 'Nguyễn Ngọc An', 'dungvd' => 'Vũ Đình Dũng',
        'dungnt' => 'Nguyễn Tiến Dũng', 'quyennv1' => 'Nguyễn Văn Quyền', 'ngochv' => 'Hoàng Văn Ngọc',
        'quangnd' => 'Nguyễn Đình Quang', 'giangvd' => 'Vũ Đỗ Minh Giang', 'anhntl' => 'Nguyễn Thị Lâm Anh',
        'ninhnk' => 'Nguyễn Khánh Ninh', 'ngocpm' => 'Phạm Minh Ngọc', 'doantd' => 'Trần Đình Đoàn',
        'hoangdx' => 'Đỗ Xuân Hoàng', 'minhvv' => 'Vũ Văn Minh', 'cuongvc' => 'Võ Cao Cường',
        'hieunt' => 'Nguyễn Trung Hiếu', 'quynd' => 'Nguyễn Đình Quý',
        // trang 5
        'hungln' => 'Lê Ngọc Hùng', 'anhnt2' => 'Nguyễn Tuấn Anh', 'linhnt' => 'Nguyễn Thị Linh',
        'hanhdt' => 'Đoàn Thị Hồng Hạnh', 'hiephnq' => 'Nguyễn Quang Hiệp', 'dungvd2' => 'Vĩ Đức Dũng',
        'hahh' => 'Hoàng Hải Hà', 'vuna' => 'Nguyễn Anh Vũ', 'sonpt' => 'Phạm Tuấn Sơn',
        'dunght' => 'Hoàng Trung Dũng', 'minhph' => 'Phạm Huyền Minh', 'namnh' => 'Nguyễn Hải Nam',
        'tuannv' => 'Nguyễn Văn Tuấn', 'quangndd' => 'Nguyễn Đại Quang', 'haivt' => 'Vũ Thanh Hải',
        'tuannd' => 'Nguyễn Đăng Tuấn', 'quangtd' => 'Trần Đại Quang', 'tuanple' => 'Phạm Lê Tuấn',
        'hieunmm' => 'Nguyễn Minh Hiếu', 'chinhnv' => 'Nguyễn Văn Chính',
        // trang 6
        'thaitd' => 'Trần Đức Thái', 'minhdq' => 'Dương Quang Minh', 'thanhnh' => 'Nguyễn Hữu Thành',
        'trungtt' => 'Tăng Tiến Trung', 'duchh' => 'Hoàng Huy Đức', 'tungtx' => 'Tô Xuân Tùng',
        'ngocvtb' => 'Vũ Thị Bích Ngọc', 'thanhnt2' => 'Nguyễn Tiến Thành', 'longdt' => 'Dương Thành Long',
        'longnh' => 'Nguyễn Hải Long', 'huyenttm' => 'Trương Thị Minh Huyền', 'haint' => 'Nguyễn Thị Hải',
        'chinhng' => 'Ngô Công Chính', 'longpd' => 'Phạm Đức Long', 'phuocclt' => 'Cao Lương Trường Phước',
        'hieuvt' => 'Vũ Tuấn Hiệp', 'nhanv' => 'Nguyễn Văn Nhã', 'linhntt' => 'Nguyễn Thị Thùy Linh',
        // trang 7
        'thanhnc' => 'Nguyễn Chí Thành', 'hoant' => 'Hoàng Thị Thanh Hoa', 'tuanda' => 'Đỗ Anh Tuấn',
        'anhpq' => 'Phan Quốc Anh', 'ngocbt' => 'Bùi Thị Thu Ngọc', 'vunbt' => 'Bùi Thế Vũ',
        'dungdv' => 'Đoàn Văn Dũng', 'thiennd' => 'Nguyễn Đức Thiện', 'thanhtv' => 'Trần Việt Thành',
        'anhdq2' => 'Đỗ Quốc Anh', 'thuylt' => 'Lê Thị Thúy', 'linhpt' => 'Phạm Thùy Linh',
        'quyentt' => 'Trần Thị Quyên', 'haknq' => 'Kiều Ngọc Hà', 'vienbv' => 'Bùi Văn Viên',
        'huynt' => 'Nguyễn Tiến Huy', 'phongpt' => 'Phạm Tuấn Phong', 'huybv' => 'Bùi Văn Huy',
        'truongpn' => 'Phùng Ngọc Trường', 'huect' => 'Cao Thị Huệ', 'cuongdq' => 'Đỗ Quốc Cường',
        'ngoclm' => 'Lê Minh Ngọc', 'datnd' => 'Nguyễn Đức Đạt', 'anhnh' => 'Nguyễn Hoàng Anh',
        'dodu' => 'Du Đỗ',
        // trang 8
        'phuongntv' => 'Nguyễn Thị Việt Phương', 'dangch' => 'Chu Hải Đăng', 'hoalt' => 'Lê Trọng Hòa',
        'phucnv' => 'Nguyễn Văn Phúc', 'thaopt' => 'Phạm Thị Thảo', 'huyennt' => 'Nguyễn Thị Ngọc Huyền',
        'anhttp' => 'Trần Thị Phương Anh', 'phuongdm' => 'Đỗ Minh Phương', 'hapt' => 'Phạm Thị Hải Hà',
        'dungbv' => 'Bùi Văn Dũng', 'liendtq' => 'Đào Thị Quỳnh Liên', 'anhnt' => 'Nguyễn Thị Anh Hoài',
        'phongnt' => 'Nguyễn Trọng Phong', 'dungnv2' => 'Nguyễn Văn Dũng', 'hoangnh' => 'Nguyễn Huy Hoàng',
        'thuongmt' => 'Mai Thị Thương', 'hiepnh' => 'Nguyễn Hữu Hiệp',
    ];
}
