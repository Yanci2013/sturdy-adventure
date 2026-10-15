#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""把 yylAdmin 的 MySQL 建表脚本转换为 SQLite 并生成数据库文件。

用法：python3 database/build_sqlite.py
输入：public/private/yyladmin.sql
输出：database/yyladmin.db
"""
import os
import re
import sqlite3

BASE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(BASE)
SRC = os.path.join(ROOT, 'public', 'private', 'yyladmin.sql')
DB = os.path.join(BASE, 'yyladmin.db')


def map_type(t):
    """MySQL 字段类型 -> SQLite 类型"""
    t = t.lower()
    if re.match(r'^(tinyint|smallint|mediumint|int|bigint)', t):
        return 'INTEGER'
    if re.match(r'^(decimal|numeric)', t):
        return 'NUMERIC'
    if re.match(r'^(float|double|real)', t):
        return 'REAL'
    if re.match(r'^(blob|binary|varbinary)', t):
        return 'BLOB'
    return 'TEXT'  # varchar/char/text/datetime/enum/json...


def find_body(stmt):
    """定位 CREATE TABLE 后的括号主体（考虑嵌套括号与字符串）"""
    start = stmt.index('(')
    depth = 0
    in_str = False
    esc = False
    for i in range(start, len(stmt)):
        c = stmt[i]
        if in_str:
            if esc:
                esc = False
            elif c == '\\':
                esc = True
            elif c == "'":
                in_str = False
            continue
        if c == "'":
            in_str = True
        elif c == '(':
            depth += 1
        elif c == ')':
            depth -= 1
            if depth == 0:
                return stmt[start + 1:i]
    return None


def clean_col(raw):
    """清理单个字段定义"""
    s = raw.strip().rstrip(',').strip()
    s = re.sub(r"\s+COMMENT\s+'(?:[^'\\]|\\.)*'", '', s)
    s = re.sub(r'\bCHARACTER SET \w+', '', s)
    s = re.sub(r'\bCOLLATE \w+', '', s)
    s = re.sub(r'\bUNSIGNED\b', '', s)
    s = re.sub(r'\bON UPDATE CURRENT_TIMESTAMP\b', '', s, flags=re.I)
    return re.sub(r'\s+', ' ', s).strip()


def convert_create(stmt):
    name = re.match(r'CREATE TABLE\s+(`?[\w]+`?)', stmt, re.I).group(1)
    body = find_body(stmt)
    cols, is_ai = [], False
    for line in body.split('\n'):
        t = clean_col(line)
        if not t:
            continue
        if re.match(r'^(PRIMARY KEY|UNIQUE KEY|KEY|INDEX|CONSTRAINT|FULLTEXT|FOREIGN KEY)\b', t, re.I):
            continue
        m = re.match(r'^(`?[\w]+`?)\s+(.*)$', t, re.S)
        if not m:
            continue
        col, rest = m.group(1), m.group(2)
        ai = 'AUTO_INCREMENT' in rest.upper()
        rest = re.sub(r'\bAUTO_INCREMENT\b', '', rest, flags=re.I)
        mt = re.match(r'^([\w]+)(\([^)]*\))?\s*(.*)$', rest, re.S)
        if not mt:
            continue
        typ, tail = map_type(mt.group(1)), mt.group(3)
        if ai and typ == 'INTEGER':
            cols.append('%s INTEGER PRIMARY KEY AUTOINCREMENT' % col)
        else:
            cols.append(('%s %s %s' % (col, typ, tail)).strip())
    return 'CREATE TABLE %s (\n  %s\n);' % (name, ',\n  '.join(cols))


def unescape(s):
    """MySQL 字符串转义 -> SQLite"""
    def rep(m):
        c = m.group(1)
        return {'n': '\n', 'r': '\r', 't': '\t', '0': '', "'": "''", '"': '"', '\\': '\\', 'Z': ''}.get(c, c)
    return re.sub(r'\\(.)', rep, s)


def main():
    with open(SRC, encoding='utf-8', errors='replace') as f:
        raw = f.read()

    stmts = re.split(r';\s*\n', raw)
    out = []
    for st in stmts:
        s = st.strip()
        if not s:
            continue
        if re.match(r'^(SET|LOCK TABLES|UNLOCK TABLES|START TRANSACTION|COMMIT|/\*)', s, re.I):
            continue
        s = re.sub(r'^--.*$', '', s, flags=re.M).strip()
        if not s:
            continue
        if re.match(r'^CREATE TABLE', s, re.I):
            out.append(convert_create(s) + '\n')
        elif re.match(r'^(INSERT|DROP TABLE|CREATE INDEX)', s, re.I):
            out.append(unescape(s) + ';\n')
    sql = '\n'.join(out)

    if os.path.exists(DB):
        os.remove(DB)
    conn = sqlite3.connect(DB)
    conn.executescript(sql)
    conn.commit()

    cur = conn.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
    tables = [r[0] for r in cur.fetchall()]
    print('数据库已生成：%s' % DB)
    for t in tables:
        n = conn.execute('SELECT COUNT(*) FROM "%s"' % t).fetchone()[0]
        print('  %-20s %d 行' % (t, n))
    conn.close()


if __name__ == '__main__':
    main()
