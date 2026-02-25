
import sqlite3
import sqlite_vec
import array

def test_vec():
    try:
        conn = sqlite3.connect(":memory:")
        conn.enable_load_extension(True)
        sqlite_vec.load(conn)
        conn.enable_load_extension(False)
        print("sqlite-vec loaded successfully")
        
        cursor = conn.cursor()
        cursor.execute("CREATE VIRTUAL TABLE vec_test USING vec0(embedding float[3])")
        
        emb1 = array.array('f', [1.0, 0.0, 0.0]).tobytes()
        emb2 = array.array('f', [0.0, 1.0, 0.0]).tobytes()
        
        cursor.execute("INSERT INTO vec_test(rowid, embedding) VALUES(1, ?)", (emb1,))
        cursor.execute("INSERT INTO vec_test(rowid, embedding) VALUES(2, ?)", (emb2,))
        
        query_emb = array.array('f', [0.9, 0.1, 0.0]).tobytes()
        cursor.execute("""
            SELECT rowid, vec_distance_cosine(embedding, ?) as distance
            FROM vec_test
            ORDER BY distance
        """, (query_emb,))
        
        results = cursor.fetchall()
        print(f"Results: {results}")
        return True
    except Exception as e:
        print(f"Error: {e}")
        return False

if __name__ == "__main__":
    test_vec()
