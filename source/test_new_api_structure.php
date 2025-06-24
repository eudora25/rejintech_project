<?php
/**
 * 새로운 API 구조 테스트
 */

// 테스트용 API 요청 데이터
$test_request = [
    "filterModel" => [
        "type" => ["CSO"]
    ],
    "sortModel" => [
        "bizName" => "desc"
    ],
    "page" => 1,
    "size" => 50
];

echo "=== 새로운 API 요청 구조 테스트 ===\n\n";
echo "요청 데이터:\n";
echo json_encode($test_request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// curl 테스트 예제
$curl_command = 'curl -X POST "http://localhost/source/index.php/api/procurement/delivery-requests" \\
     -H "Content-Type: application/json" \\
     -H "Authorization: Bearer YOUR_JWT_TOKEN" \\
     -d \'' . json_encode($test_request) . '\'';

echo "cURL 테스트 명령어:\n";
echo $curl_command . "\n\n";

// 복합 필터 테스트
$complex_request = [
    "filterModel" => [
        "type" => ["CSO", "MAS"],
        "dminsttNm" => "서울특별시",
        "dateFrom" => "2024-01-01",
        "dateTo" => "2024-12-31"
    ],
    "sortModel" => [
        "dlvrReqRcptDate" => "desc",
        "incdecAmt" => "desc"
    ],
    "page" => 1,
    "size" => 100
];

echo "=== 복합 필터 테스트 요청 ===\n\n";
echo "요청 데이터:\n";
echo json_encode($complex_request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "주요 변경사항:\n";
echo "1. GET → POST 메서드로 변경\n";
echo "2. 쿼리 파라미터 → JSON 요청 본문으로 변경\n";
echo "3. pageSize → size로 파라미터명 변경\n";
echo "4. filterModel 객체로 필터 조건 그룹화\n";
echo "5. sortModel 객체로 정렬 조건 구조화\n";
echo "6. type 필터 배열로 CSO/MAS 다중 선택 지원\n";
echo "7. bizName 필드 추가 (업체명 정렬용)\n\n";

echo "지원되는 type 값:\n";
echo "- CSO: 우수제품 (is_excellent_product = 1)\n";
echo "- MAS: 일반제품 (is_excellent_product = 0)\n\n";

echo "지원되는 정렬 필드:\n";
echo "- bizName: 업체명\n";
echo "- dlvrReqRcptDate: 납품요구접수일자\n";
echo "- dminsttNm: 수요기관명\n";
echo "- corpNm: 업체명\n";
echo "- incdecAmt: 증감금액\n";
echo "- prdctUprc: 물품단가\n\n";

echo "정렬 방향: asc (오름차순), desc (내림차순)\n";
?> 