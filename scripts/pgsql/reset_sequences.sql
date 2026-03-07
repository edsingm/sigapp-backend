CREATE OR REPLACE FUNCTION public.reset_all_sequences_for_schema(target_schema text)
RETURNS void
LANGUAGE plpgsql
AS $$
DECLARE
    rec record;
BEGIN
    FOR rec IN
        SELECT
            n.nspname AS schema_name,
            c.relname AS table_name,
            a.attname AS column_name,
            pg_get_serial_sequence(format('%I.%I', n.nspname, c.relname), a.attname) AS seq_name
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        JOIN pg_attribute a ON a.attrelid = c.oid
        JOIN pg_attrdef ad ON ad.adrelid = c.oid AND ad.adnum = a.attnum
        WHERE c.relkind = 'r'
          AND n.nspname = target_schema
          AND a.attnum > 0
          AND NOT a.attisdropped
          AND pg_get_serial_sequence(format('%I.%I', n.nspname, c.relname), a.attname) IS NOT NULL
    LOOP
        EXECUTE format(
            'SELECT setval(%L, COALESCE((SELECT MAX(%I) FROM %I.%I), 0) + 1, false)',
            rec.seq_name,
            rec.column_name,
            rec.schema_name,
            rec.table_name
        );
    END LOOP;
END;
$$;

SELECT public.reset_all_sequences_for_schema('public');

DO $$
DECLARE
    s record;
BEGIN
    FOR s IN
        SELECT schema_name
        FROM information_schema.schemata
        WHERE schema_name LIKE 'tenant\_%' ESCAPE '\'
    LOOP
        PERFORM public.reset_all_sequences_for_schema(s.schema_name);
    END LOOP;
END;
$$;
