#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
조달청 납품요구 API 테스트 스크립트 (엑셀 스펙 검증)

엑셀 파일 "요청 API 목록" 시트의 스펙에 맞춰 구현된 API를 테스트합니다.
"""

import requests
import json
import sys
from datetime import datetime

class ProcurementAPITester:
    def __init__(self, base_url="http://localhost"):
        self.base_url = base_url.rstrip('/')
        self.token = None
        
    def login(self, username="admin", password="admin123"):
        """로그인하여 토큰 획득"""
        login_url = f"{self.base_url}/api/auth/login"
        login_data = {
            "username": username,
            "password": password
        }
        
        try:
            response = requests.post(login_url, json=login_data)
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    self.token = result['data']['token']
                    print(f"✅ 로그인 성공: {username}")
                    return True
                else:
                    print(f"❌ 로그인 실패: {result.get('message')}")
                    return False
            else:
                print(f"❌ 로그인 HTTP 오류: {response.status_code}")
                return False
        except Exception as e:
            print(f"❌ 로그인 예외: {e}")
            return False
    
    def test_delivery_requests_basic(self):
        """기본 조회 테스트 (엑셀 스펙)"""
        print("\n=== 기본 조회 테스트 ===")
        
        url = f"{self.base_url}/api/procurement/delivery-requests"
        headers = {"Authorization": f"Bearer {self.token}"}
        
        # 엑셀 스펙 기본 파라미터
        params = {
            "page": 1,
            "pageSize": 10
        }
        
        try:
            response = requests.get(url, headers=headers, params=params)
            print(f"HTTP 상태코드: {response.status_code}")
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    data = result['data']
                    print(f"✅ 기본 조회 성공")
                    print(f"   - 페이지: {data.get('page')}")
                    print(f"   - 페이지 크기: {data.get('pageSize')}")
                    print(f"   - 총 건수: {data.get('total')}")
                    print(f"   - 전체 금액: {data.get('totalAmount'):,}")
                    print(f"   - 조달 금액: {data.get('jodalTotalAmount'):,}")
                    print(f"   - 마스 금액: {data.get('masTotalAmount'):,}")
                    print(f"   - 데이터 건수: {len(data.get('items', []))}")
                    
                    # 첫 번째 아이템 구조 확인
                    items = data.get('items', [])
                    if items:
                        first_item = items[0]
                        print(f"   - 첫 번째 아이템 필드: {list(first_item.keys())}")
                        
                    return True
                else:
                    print(f"❌ 기본 조회 실패: {result.get('message')}")
                    return False
            else:
                print(f"❌ HTTP 오류: {response.status_code}")
                print(f"   응답: {response.text}")
                return False
                
        except Exception as e:
            print(f"❌ 예외 발생: {e}")
            return False
    
    def test_delivery_requests_filters(self):
        """필터 조회 테스트 (엑셀 스펙)"""
        print("\n=== 필터 조회 테스트 ===")
        
        url = f"{self.base_url}/api/procurement/delivery-requests"
        headers = {"Authorization": f"Bearer {self.token}"}
        
        # 엑셀 스펙 필터 파라미터들
        test_cases = [
            {
                "name": "날짜 필터",
                "params": {
                    "page": 1,
                    "pageSize": 5,
                    "startDate": "2024-01-01",
                    "endDate": "2024-12-31"
                }
            },
            {
                "name": "구분 필터 (조달)",
                "params": {
                    "page": 1,
                    "pageSize": 5,
                    "exclcProdctYn": "조달"
                }
            },
            {
                "name": "품명 검색",
                "params": {
                    "page": 1,
                    "pageSize": 5,
                    "prdctClsfcNoNmSearch": "감시"
                }
            },
            {
                "name": "수요기관 검색",
                "params": {
                    "page": 1,
                    "pageSize": 5,
                    "dminsttNm": "경기도"
                }
            },
            {
                "name": "정렬 테스트",
                "params": {
                    "page": 1,
                    "pageSize": 5,
                    "sortBy": "dlvrReqRcptDate",
                    "sortOrder": "desc"
                }
            }
        ]
        
        for test_case in test_cases:
            print(f"\n--- {test_case['name']} ---")
            
            try:
                response = requests.get(url, headers=headers, params=test_case['params'])
                
                if response.status_code == 200:
                    result = response.json()
                    if result.get('success'):
                        data = result['data']
                        print(f"✅ {test_case['name']} 성공")
                        print(f"   - 조회 건수: {len(data.get('items', []))}")
                        print(f"   - 전체 건수: {data.get('total')}")
                    else:
                        print(f"❌ {test_case['name']} 실패: {result.get('message')}")
                else:
                    print(f"❌ {test_case['name']} HTTP 오류: {response.status_code}")
                    
            except Exception as e:
                print(f"❌ {test_case['name']} 예외: {e}")
    
    def test_error_responses(self):
        """오류 응답 테스트 (엑셀 스펙)"""
        print("\n=== 오류 응답 테스트 ===")
        
        url = f"{self.base_url}/api/procurement/delivery-requests"
        
        # 토큰 없이 요청 (401 예상)
        print("\n--- 인증 오류 테스트 ---")
        try:
            response = requests.get(url, params={"page": 1, "pageSize": 10})
            print(f"HTTP 상태코드: {response.status_code}")
            
            if response.status_code == 401:
                print("✅ 인증 오류 정상 처리")
            else:
                print(f"❌ 예상과 다른 상태코드: {response.status_code}")
                
        except Exception as e:
            print(f"❌ 예외: {e}")
        
        # 잘못된 파라미터 테스트
        print("\n--- 파라미터 오류 테스트 ---")
        headers = {"Authorization": f"Bearer {self.token}"}
        invalid_params = {
            "page": -1,  # 잘못된 페이지
            "pageSize": 200,  # 범위 초과
            "startDate": "invalid-date"  # 잘못된 날짜 형식
        }
        
        try:
            response = requests.get(url, headers=headers, params=invalid_params)
            print(f"HTTP 상태코드: {response.status_code}")
            
            if response.status_code == 400:
                result = response.json()
                print(f"✅ 파라미터 오류 정상 처리: {result.get('message')}")
            else:
                print(f"❌ 예상과 다른 상태코드: {response.status_code}")
                
        except Exception as e:
            print(f"❌ 예외: {e}")
    
    def run_all_tests(self):
        """모든 테스트 실행"""
        print("🚀 조달청 납품요구 API 테스트 시작 (엑셀 스펙 검증)")
        print(f"기본 URL: {self.base_url}")
        print(f"테스트 시간: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        
        # 로그인
        if not self.login():
            print("❌ 로그인 실패로 테스트 중단")
            return False
        
        # 테스트 실행
        tests = [
            self.test_delivery_requests_basic,
            self.test_delivery_requests_filters,
            self.test_error_responses
        ]
        
        success_count = 0
        for test in tests:
            try:
                if test():
                    success_count += 1
            except Exception as e:
                print(f"❌ 테스트 예외: {e}")
        
        print(f"\n📊 테스트 결과: {success_count}/{len(tests)} 성공")
        return success_count == len(tests)

def main():
    if len(sys.argv) > 1:
        base_url = sys.argv[1]
    else:
        base_url = "http://localhost"
    
    tester = ProcurementAPITester(base_url)
    success = tester.run_all_tests()
    
    if success:
        print("\n🎉 모든 테스트 통과!")
        sys.exit(0)
    else:
        print("\n💥 일부 테스트 실패")
        sys.exit(1)

if __name__ == "__main__":
    main() 