#!/bin/bash

# Swagger UI OpenAPI JSON 업데이트 스크립트
# Usage: ./scripts/update_swagger.sh

echo "🔄 Swagger UI OpenAPI JSON 업데이트 중..."

# 소스 파일과 타겟 파일 경로
SOURCE_FILE="source/api/docs/openapi.json"
TARGET_FILE="source/swagger-ui/api/docs/openapi.json"

# 소스 파일 존재 확인
if [ ! -f "$SOURCE_FILE" ]; then
    echo "❌ 소스 파일을 찾을 수 없습니다: $SOURCE_FILE"
    exit 1
fi

# 타겟 디렉토리 생성
mkdir -p "$(dirname "$TARGET_FILE")"

# 파일 복사
cp "$SOURCE_FILE" "$TARGET_FILE"

if [ $? -eq 0 ]; then
    echo "✅ OpenAPI JSON 파일이 성공적으로 업데이트되었습니다."
    echo "   소스: $SOURCE_FILE"
    echo "   타겟: $TARGET_FILE"
    
    # 파일 크기와 수정 시간 출력
    echo ""
    echo "📊 파일 정보:"
    ls -lh "$TARGET_FILE"
    
    # JSON 유효성 검사 (jq가 설치된 경우)
    if command -v jq &> /dev/null; then
        echo ""
        echo "🔍 JSON 유효성 검사 중..."
        if jq empty "$TARGET_FILE" 2>/dev/null; then
            echo "✅ JSON 형식이 유효합니다."
        else
            echo "❌ JSON 형식에 오류가 있습니다."
            exit 1
        fi
    fi
    
    echo ""
    echo "🚀 Swagger UI에서 확인하세요: http://localhost/source/swagger-ui/"
    
else
    echo "❌ 파일 복사에 실패했습니다."
    exit 1
fi 