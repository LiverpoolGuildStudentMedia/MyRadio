BEGIN;

ALTER TABLE public.terms
    ADD COLUMN weeks INT DEFAULT 10;

COMMIT;