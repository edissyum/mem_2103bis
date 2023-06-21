CREATE TABLE public.contacts_civilities (
    id integer NOT NULL,
    label text NOT NULL,
    abbreviation character varying(16) NOT NULL
);
CREATE SEQUENCE public.contacts_civilities_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE public.contacts_civilities_id_seq OWNED BY public.contacts_civilities.id;

ALTER TABLE ONLY public.contacts_civilities ALTER COLUMN id SET DEFAULT nextval('public.contacts_civilities_id_seq'::regclass);
INSERT INTO public.contacts_civilities VALUES (1, 'Monsieur', 'M.');
INSERT INTO public.contacts_civilities VALUES (2, 'Madame', 'Mme');
INSERT INTO public.contacts_civilities VALUES (3, 'Mademoiselle', 'Mlle');
INSERT INTO public.contacts_civilities VALUES (4, 'Messieurs', 'MM.');
INSERT INTO public.contacts_civilities VALUES (5, 'Mesdames', 'Mmes');
INSERT INTO public.contacts_civilities VALUES (6, 'Mesdemoiselles', 'Mlles');

SELECT pg_catalog.setval('public.contacts_civilities_id_seq', 6, true);
ALTER TABLE ONLY public.contacts_civilities
    ADD CONSTRAINT contacts_civilities_pkey PRIMARY KEY (id);