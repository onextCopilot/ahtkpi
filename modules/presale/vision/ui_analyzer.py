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
        # Sử dụng model nano để đảm bảo tốc độ trên CPU
        # Nếu có GPU, nó sẽ tự động sử dụng
        try:
            self.model = YOLO('yolov8n.pt') 
            self.reader = easyocr.Reader(['vi', 'en'], gpu=False)
        except Exception as e:
            print(json.dumps({"success": False, "message": str(e)}))
            sys.exit(1)

    def analyze(self, image_path):
        if not os.path.exists(image_path):
            return {"success": False, "message": "File không tồn tại"}

        image = cv2.imread(image_path)
        if image is None:
            return {"success": False, "message": "Không thể đọc ảnh"}

        h, w, _ = image.shape
        results = self.model(image)
        
        raw_elements = []
        for result in results:
            for box in result.boxes:
                x1, y1, x2, y2 = box.xyxy[0].tolist()
                cls = int(box.cls[0].item())
                raw_elements.append({
                    "type": self.model.names[cls],
                    "box": [x1, y1, x2, y2],
                    "y1": y1,
                    "y2": y2
                })

        # Thuật toán gom nhóm theo trục Y
        raw_elements.sort(key=lambda x: x['y1'])
        
        sections = []
        if raw_elements:
            current_section = [raw_elements[0]]
            threshold = h * 0.15 # Ngưỡng 15% chiều cao ảnh

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
            
            # Cắt vùng ảnh section để OCR
            section_img = image[min_y:max_y, 0:w]
            
            try:
                texts = self.reader.readtext(section_img)
                all_text = " ".join([t[1] for t in texts]).lower()
            except:
                all_text = ""

            # Logic định danh đơn giản
            section_name = f"Khối nội dung {i+1}"
            if any(k in all_text for k in ["cart", "giỏ hàng", "thanh toán", "checkout"]):
                section_name = "Thanh toán / Giỏ hàng"
            elif any(k in all_text for k in ["login", "đăng nhập", "đăng ký", "sign up", "email", "password"]):
                section_name = "Authentication (Đăng nhập/Ký)"
            elif i == 0 and min_y < h * 0.2:
                section_name = "Header / Navigation bar"
            elif i == len(sections) - 1 and max_y > h * 0.8:
                section_name = "Footer / Social Media"
            elif any(k in all_text for k in ["product", "sản phẩm", "giá", "price", "shop"]):
                section_name = "Danh sách Sản phẩm / Services"
            elif any(k in all_text for k in ["contact", "liên hệ", "address", "địa chỉ"]):
                section_name = "Liên hệ / Bản đồ"

            final_structure.append({
                "name": section_name,
                "elements_count": len(elements),
                "summary": all_text[:200]
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
