import cv2
import easyocr
import json
import sys
import os
import numpy as np
from ultralytics import YOLO

# Tắt thông báo log của YOLO để output JSON sạch
os.environ['YOLO_VERBOSE'] = 'False'

class UISectionAnalyzer:
    def __init__(self):
        try:
            # Model nano đảm bảo tốc độ trên CPU
            self.model = YOLO('yolov8n.pt') 
            self.reader = easyocr.Reader(['vi', 'en'], gpu=False)
        except Exception as e:
            print(json.dumps({"success": False, "message": str(e)}))
            sys.exit(1)

    def _guess_section_name(self, text, index, total_sections, min_y, h):
        text = text.lower()
        keywords = {
            "Header (Menu, Search, Language)": ["home", "menu", "search", "logo", "language", "en", "vi", "login", "đăng nhập", "account", "giỏ hàng", "cart"],
            "Banner / Slider": ["banner", "slider", "hero", "welcome", "promotion", "off", "%", "khuyến mãi", "shop now", "discover"],
            "Product List / Grid": ["price", "giá", "$", "₫", "add to cart", "mua ngay", "stock", "product", "sản phẩm", "description"],
            "Authentication Form": ["email", "password", "mật khẩu", "forgot", "sign in", "đăng ký", "register", "username"],
            "Contact / Info Form": ["name", "phone", "điện thoại", "message", "subject", "submit", "gửi liên hệ"],
            "Footer / Company Info": ["copyright", "rights reserved", "address", "địa chỉ", "follow us", "facebook", "instagram", "policy", "điều khoản"]
        }
        
        # Kiểm tra theo vị trí trước
        if index == 0 and min_y < h * 0.15:
            return "Header (Menu, Search, Language)"
        if index == total_sections - 1 and min_y > h * 0.8:
            return "Footer / Company Info"

        scores = {}
        for section, keys in keywords.items():
            score = sum(1 for key in keys if key in text)
            if score > 0:
                scores[section] = score
        
        if scores:
            return max(scores, key=scores.get)
        
        return f"Khối nội dung {index + 1}"

    def analyze(self, image_path):
        if not os.path.exists(image_path):
            return {"success": False, "message": "File không tồn tại"}

        image = cv2.imread(image_path)
        if image is None:
            return {"success": False, "message": "Không thể đọc ảnh"}

        h, w, _ = image.shape
        results = self.model(image, verbose=False)
        
        raw_elements = []
        for r in results:
            for box in r.boxes:
                coords = box.xyxy[0].tolist()
                cls_id = int(box.cls[0])
                cls_name = self.model.names[cls_id]
                raw_elements.append({
                    'y1': coords[1], 'y2': coords[3], 
                    'x1': coords[0], 'x2': coords[2], 
                    'class': cls_name
                })

        # Sắp xếp theo trục Y để gom nhóm
        raw_elements.sort(key=lambda x: x['y1'])

        sections = []
        if raw_elements:
            current_section = [raw_elements[0]]
            threshold = h * 0.12 # Ngưỡng khoảng cách giữa các khối

            for i in range(1, len(raw_elements)):
                if raw_elements[i]['y1'] - current_section[-1]['y2'] > threshold:
                    sections.append(current_section)
                    current_section = [raw_elements[i]]
                else:
                    current_section.append(raw_elements[i])
            sections.append(current_section)

        final_structure = []
        for i, elements in enumerate(sections):
            min_y = max(0, int(min([e['y1'] for e in elements]) - 10))
            max_y = min(h, int(max([e['y2'] for e in elements]) + 10))
            
            section_img = image[min_y:max_y, 0:w]
            
            try:
                # OCR cho vùng khối nội dung
                ocr_data = self.reader.readtext(section_img)
                all_text = " ".join([t[1] for t in ocr_data]).lower()
            except:
                all_text = ""

            section_name = self._guess_section_name(all_text, i, len(sections), min_y, h)
            
            # Liệt kê các thành phần con AI nhìn thấy (button, image, icon...)
            components = list(set([e['class'] for e in elements]))
            
            final_structure.append({
                "name": section_name,
                "components": components,
                "summary": f"{section_name} gồm các thành phần: {', '.join(components)}. Nội dung văn bản: " + (all_text[:300] if all_text else "Không có văn bản")
            })

        return {"success": True, "data": final_structure}

if __name__ == "__main__":
    if len(sys.argv) > 1:
        img_path = sys.argv[1]
        analyzer = UISectionAnalyzer()
        res = analyzer.analyze(img_path)
        print(json.dumps(res, ensure_ascii=False))
    else:
        print(json.dumps({"success": False, "message": "Thiếu tham số đường dẫn ảnh"}))
