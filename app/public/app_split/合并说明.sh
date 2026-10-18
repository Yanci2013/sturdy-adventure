#!/bin/bash
# Windows 用: copy /b app_full_source.zip.part_00+app_full_source.zip.part_01+... app_full_source.zip
# Linux/Mac 用: cat app_full_source.zip.part_* > app_full_source.zip
cat app_full_source.zip.part_* > app_full_source.zip
echo "合并完成: app_full_source.zip"
unzip -l app_full_source.zip | tail -1
