"""Проверка фактического SQL счётчика на SQLite (не заменяет тест на MySQL/WordPress)."""
import pathlib
import re
import sqlite3

source = (pathlib.Path(__file__).parent.parent / 'includes/class-wppa-chat.php').read_text()
query = re.search(r'SELECT /\* WPPA_CHAT_UNREAD \*/.*?GROUP BY p\.post_author', source, re.S).group(0)
for key in ('posts', 'postmeta', 'usermeta'):
    query = query.replace('{$wpdb->' + key + '}', 'wp_' + key)
query = query.replace('%s', '?').replace('%d', '?')
db = sqlite3.connect(':memory:')
db.create_function('CONCAT', 2, lambda a, b: str(a) + str(b))
db.executescript('''
CREATE TABLE wp_posts (ID INTEGER, post_author INTEGER, post_type TEXT, post_status TEXT);
CREATE TABLE wp_postmeta (post_id INTEGER, meta_key TEXT, meta_value TEXT);
CREATE TABLE wp_usermeta (user_id INTEGER, meta_key TEXT, meta_value TEXT);
''')
db.executemany('INSERT INTO wp_posts VALUES (?,?,?,?)', [
    (101, 8, 'wppa_chat_message', 'private'), (102, 12, 'wppa_chat_message', 'private'),
    (103, 8, 'wppa_chat_message', 'private'), (104, 9, 'wppa_chat_message', 'private'),
    (105, 8, 'wppa_chat_message', 'draft'), (106, 8, 'post', 'private')])
db.executemany('INSERT INTO wp_postmeta VALUES (?,?,?)', [(i, '_wppa_chat_mentor', str(m)) for i,m in [(101,8),(102,8),(103,8),(104,9),(105,8),(106,8),(103,8)]])
db.execute('INSERT INTO wp_usermeta VALUES (?,?,?)', (12, '_wppa_chat_read_1_8', '101'))
def counts(uid):
    return dict(db.execute(query, ('_wppa_chat_mentor', uid, '_wppa_chat_read_1_', 'wppa_chat_message')).fetchall())
assert counts(12) == {8: 1, 9: 1}, counts(12)
assert counts(13) == {8: 2, 9: 1}, counts(13)
db.execute('UPDATE wp_usermeta SET meta_value = ? WHERE user_id = ?', ('103', 12))
assert counts(12) == {9: 1}, counts(12)
assert counts(13) == {8: 2, 9: 1}, counts(13)
db.execute('INSERT INTO wp_usermeta VALUES (?,?,?)', (12, '_wppa_chat_read_1_8', '101'))
assert counts(12) == {9: 1}, counts(12)  # При конкуренции возможны повторные метаключи: применяется максимальная отметка.
query_mentor = re.search(r'SELECT /\* WPPA_CHAT_MENTOR_UNREAD \*/.*?meta_key = %s\), 0\)', source, re.S).group(0)
for key in ('posts', 'postmeta', 'usermeta'):
    query_mentor = query_mentor.replace('{$wpdb->' + key + '}', 'wp_' + key)
query_mentor = query_mentor.replace('%s', '?').replace('%d', '?')
def mentor_count(uid):
    return db.execute(query_mentor, ('_wppa_chat_mentor', str(uid), 'wppa_chat_message', uid, uid, '_wppa_chat_read_1_' + str(uid))).fetchone()[0]
assert mentor_count(8) == 1, mentor_count(8)  # Ответ куратора, а не собственные реплики.
db.execute('INSERT INTO wp_usermeta VALUES (?,?,?)', (8, '_wppa_chat_read_1_8', '102'))
assert mentor_count(8) == 0, mentor_count(8)
db.execute("INSERT INTO wp_posts VALUES (107, 13, 'wppa_chat_message', 'private')")
db.execute("INSERT INTO wp_postmeta VALUES (107, '_wppa_chat_mentor', '8')")
assert mentor_count(8) == 1, mentor_count(8)
print('OK: SQL счётчиков обеих сторон, граница прочтения, кураторы, дубли метаданных')
