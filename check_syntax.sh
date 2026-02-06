#!/bin/bash
# Check PHP syntax of all calendar plugin files

echo "Checking PHP syntax..."
echo ""

errors=0

for file in *.php; do
    if [ -f "$file" ]; then
        result=$(php -l "$file" 2>&1)
        if [ $? -eq 0 ]; then
            echo "✅ $file"
        else
            echo "❌ $file"
            echo "   $result"
            errors=$((errors + 1))
        fi
    fi
done

echo ""
if [ $errors -eq 0 ]; then
    echo "✅ All PHP files are valid!"
    exit 0
else
    echo "❌ Found $errors file(s) with syntax errors"
    exit 1
fi
