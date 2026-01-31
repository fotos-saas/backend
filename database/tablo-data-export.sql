--
-- PostgreSQL database dump
--

\restrict uDSp9r2TtSROOEbfsBBmgOEO7AEaI5BFacAbUfmPeGkgg8zmjsr7s6vjBwkMIMI

-- Dumped from database version 16.11
-- Dumped by pg_dump version 16.11

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: tablo_api_keys; Type: TABLE DATA; Schema: public; Owner: photo_stack
--

COPY public.tablo_api_keys (id, name, key, is_active, last_used_at, created_at, updated_at) FROM stdin;
1	Legacy System	hWGnMcdA2NLy9L8q9rtuZhmf7Q0lQqNhKBc3fXd5KIKS6NvZDsVjuKzCRzbNlVjl	t	2025-12-04 06:27:12	2025-11-30 11:19:58	2025-12-04 06:27:12
\.


--
-- Data for Name: tablo_partners; Type: TABLE DATA; Schema: public; Owner: photo_stack
--

COPY public.tablo_partners (id, name, slug, local_id, created_at, updated_at) FROM stdin;
1	Ballagásitabló	ballagasitablohu	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
2	Fotocenter Stúdió	fotocenter-studio	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
3	Tablókirály	tablokiraly	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
4	New Age	newage	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
5	Iskolaévkönyv	iskolaevkonyv	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
\.


--
-- Data for Name: tablo_schools; Type: TABLE DATA; Schema: public; Owner: photo_stack
--

COPY public.tablo_schools (id, local_id, name, city, created_at, updated_at) FROM stdin;
1	2	Arany János Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
2	3	Babits Mihály Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
3	4	BMSZC Than Károly Ökoiskola és Technikum	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
4	5	Boronkay György Műszaki Szakgimnázium és Gimnázium, Vác	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
5	6	Budai Nagy Antal Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
6	7	Bródy Imre Gimnázium és Általános Iskola	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
7	8	Budai Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
8	9	Budai Technikum	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
9	10	Corvin Mátyás Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
10	11	Fazekas Mihály Gyakorló Ált. Isk és Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
11	12	Illyés Gyula Gimnázium, Budaörs	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
12	13	Kölcsey Ferenc Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
13	14	Móricz Zsigmond Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
14	15	Nagy Sándor József Gimnázium, Budakeszi	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
15	16	Petőfi Sándor Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
16	17	Szent István Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
17	18	Szent László Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
18	19	Táncsics Mihály Mezőgazdasági Technikum	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
19	20	Vak Bottyán János Katolikus Technikum, Gyöngyös	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
20	21	Váci Madách Imre Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
21	22	Zrínyi Miklós Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
22	103	Szigetszentmiklósi Batthyány Kázmér Gimnázium	Szigetszentmiklós	2025-11-28 05:12:26	2025-11-28 05:12:26
23	55	Teszt Iskola	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
24	56	BGSzC Budai Technikum	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
25	57	Árpád Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
26	58	Sashegyi Arany János Általános Iskola és Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
27	59	Vak Bottyán János Katolikus Technikum, Gyöngyös	Gyöngyös	2025-11-28 05:12:26	2025-11-28 05:12:26
28	60	Illyés Gyula Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
29	61	Vak Bottyán János Katolikus Műszaki és Közgazdasági Technikum,Gimnázium és Kollégium	Gyöngyös	2025-11-28 05:12:26	2025-11-28 05:12:26
30	62	Vak Bottyán János Katolikus Műszaki és Közgazdasági Technikum, Gimnázium és Kollégium	Gyöngyös	2025-11-28 05:12:26	2025-11-28 05:12:26
31	63	Arany János Református Gimnázium, Technikum és Kollégium	Nagykőrös	2025-11-28 05:12:26	2025-11-28 05:12:26
32	64	Váci Madách Imre Gimnázium	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
33	65	BGSZC Budai Gimnázium és Szakgimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
34	66	Vak Bottyán János Katolikus Műszaki és Közgazdasági Technikum, Gimnázium és Kollégium.	Gyöngyös	2025-11-28 05:12:26	2025-11-28 05:12:26
35	67	Táncsics Mihály Mezőgazdasági Technikum Szakképző és Kollégium	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
36	68	Patkós Irma Művészeti Iskola, Gimnázium, Szakgimnázium és AMI	Cegléd	2025-11-28 05:12:26	2025-11-28 05:12:26
37	69	Kecskeméti SzC Kada Elek Technikum	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
38	70	Déli ASzC Kiskunfélegyházi Mezőgazdasági és Élelmiszeripari Technikum, Szakképző Iskola és Kollégium,  Félegyházi Mezgé	Kiskunfélegyház	2025-11-28 05:12:26	2025-11-28 05:12:26
39	71	Déli ASzC Kocsis Pál Mezőgazdasági és Környezetvédelmi Technikum és Szakképző Iskola	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
40	72	Illyés Gyula Gimnázium, Szakgimnázium és Technikum	Budaörs	2025-11-28 05:12:26	2025-11-28 05:12:26
41	73	Boronkay György Műszaki Technikum és Gimnázium, Vác	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
42	74	Kecskeméti SZC Gáspár András Technikum	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
43	75	Kecskeméti SZC Kandó Kálmán Technikum	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
44	76	Kecskeméti SZC Széchenyi István Technikum	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
45	77	Közép-magyarországi Agrárszakképzési Centrum Toldi Miklós Élelmiszeripari Technikum, Szakképző Iskola és Kollégium	Nagykőrös	2025-11-28 05:12:26	2025-11-28 05:12:26
46	78	Kecskeméti Bolyai János Gimnázium	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
47	79	Kecskeméti Református Gimnázium	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
48	80	KSZC Kiskunfélegyházi Közgazdasági Technikum	Kiskunfélegyháza	2025-11-28 05:12:26	2025-11-28 05:12:26
49	81	Bányai Júlia Gimnázium	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
50	82	VSZC Boronkay György Műszaki Szakgimnázium és Gimnázium, Vác	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
51	83	Kecskeméti Kodály Iskola	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
52	84	Szent Gellért Katolikus Általános Iskola és Óvoda	Kiskunmajsa	2025-11-28 05:12:26	2025-11-28 05:12:26
53	85	Kecskeméti Piarista Gimnázium	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
54	86	Kecskeméti SzC Szent-Györgyi Albert Technikum	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
55	87	VSzC Boronkay György Műszaki Technikum és Gimnázium	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
56	88	Than Károly Ökoiskola és Technikum Rendészeti Szakgimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
57	89	Kiskunhalasi SZC Kiskunfélegyházi Kossuth Lajos Technikum, Szakképző Iskola és Kollégium	Kiskunhalas	2025-11-28 05:12:26	2025-11-28 05:12:26
58	90	CsTÁI    Piroskavárosi   Iskolája	Csongrád	2025-11-28 05:12:26	2025-11-28 05:12:26
59	91	Kerekegyházi Móra Ferenc Általános Iskola és Alapfokú Művészeti Iskola	Kerekegyháza	2025-11-28 05:12:26	2025-11-28 05:12:26
60	92	Tiszaalpári Árpád Fejedelem Általános Iskola	Tiszaalpár	2025-11-28 05:12:26	2025-11-28 05:12:26
61	93	Batthyány Kázmér Gimnázium	Szigetszentmiklós	2025-11-28 05:12:26	2025-11-28 05:12:26
62	94	Bernáth Kálmán Református Gimnázium, Szakközépiskola	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
63	95	I. Géza Király Közgazdasági Technikum	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
64	96	Kölcsey Ferenc Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
65	97	Kreatív Technikum	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
66	98	Kőrösi Csoma Sándor Általános Iskola és Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
67	99	Leövey Klára Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
68	100	Radnóti Miklós Gimnázium	Dunakeszi	2025-11-28 05:12:26	2025-11-28 05:12:26
69	101	Selye János EgészségügyiKözépiskola	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
70	102	Teleki Blanka Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
71	104	Budapest XIV.Kerületi Teleki Blanka Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
72	105	Budapest VI. Kerületi Kölcsey Ferenc Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
73	106	Budaörsi Illyés Gyula Gimnázium	Budaörs	2025-11-28 05:12:26	2025-11-28 05:12:26
74	107	Dunakeszi Radnóti Miklós Gimnázium	Dunakeszi	2025-11-28 05:12:26	2025-11-28 05:12:26
75	108	Újpesti Babits Mihály Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
76	109	Óbudai Árpád Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
77	110	Komáromi Jókai Mór Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
78	111	ELTE Apáczai Csere János Gyakorló Gimnáziuma	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
79	112	Fazekas Mihály Gyakorló Általános Iskola és Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
80	113	Közép-magyarországi ASZC Táncsics Mihály Mezőgazdasági Technikum, Szakképző Iskola és Kollégium	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
81	114	Komáromi Jókai Mór Gimnázium	Komárom	2025-11-28 05:12:26	2025-11-28 05:12:26
82	115	Gáspár András Technikum	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
83	116	Váci Szakképzési Centrum I. Géza Király Közgazdasági Technikum	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
84	117	Bernáth Kálmán Református Gimnázium, Kereskedelmi és Vendéglátóipari Technikum és Szakképző Iskola	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
85	118	Illyés Gyula Gimnázium	Budaörs	2025-11-28 05:12:26	2025-11-28 05:12:26
86	119	VSZC I. Géza Király Közgazdasági Technikum	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
87	120	BKSZC Kreatív Technikum	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
88	121	Fasori Evangélikus Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
89	122	Táncsics Mihály Mezőgazdasági Technikum	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
90	123	Budapesti Fazekas Mihály Gyakorló Általános Iskola és Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
91	126	Pilisvörösvár Friedrich Schilleg Gimnázium	Pilisvörösvár	2025-11-28 05:12:26	2025-11-28 05:12:26
92	125	Pasaréti Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
93	127	Budapest X. Kerületi Zrínyi Miklós Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
94	128	Friedrich Schiller Gimnázium és Kollégium	Pilisvörösvár	2025-11-28 05:12:26	2025-11-28 05:12:26
95	129	VSZC Boronkay György Műszaki Technikum és Gimnázium, Vác	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
96	130	Kecskeméti Katona József Gimnázium	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
97	131	(I. kerületi) Petőfi Sándor Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
98	132	Szigetszentmikósi Batthyányi Kázmér Gimnázium	Szigetszentmiklós	2025-11-28 05:12:26	2025-11-28 05:12:26
99	133	Budapest II. Kerületi Móricz Zsigmond Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
100	134	Friedrich Schiller Gimnázium	Pilisvörösvár	2025-11-28 05:12:26	2025-11-28 05:12:26
101	135	Szentendrei Móricz Zsigmond Gimnázium	Szentendre	2025-11-28 05:12:26	2025-11-28 05:12:26
102	136	Könyves Kálmán Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
103	137	Illyés Gyula Gimnázium, Budaörs	Budaörs	2025-11-28 05:12:26	2025-11-28 05:12:26
104	138	Újpesti Bródy Imre Gimnázium és Általános Iskola	-	2025-11-28 05:12:26	2025-11-28 05:12:26
105	139	Budapest-Fasori Evangélikus Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
106	140	Budapest - Fasori Evangélikus Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
107	141	Janikovszky Éva Általános Iskola	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
108	142	Váci Szakképzési Centrum Boronkay György Műszaki Technikum és Gimnázium	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
109	143	Janikovszky Éva Magyar-Angol Két Tanítási Nyelvű Általános Iskola	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
110	144	ELTE Apáczai Csere János Gyakorló Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
111	145	Sashegyi Arany János Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
112	146	Kőbányai Magyar-Angol Két Tanítási Nyelvű Általános Iskola	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
113	147	Petőfi Sándor Katolikus Általános Iskola és Óvoda	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
114	148	Szentistvántelepi Általános Iskola	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
115	149	Szentistvántelepi Általános Iskola	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
116	150	Selye János Egészségügyi Technikum	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
117	151	VSZC Boronkay György Műszaki Szakgimnázium és Gimnázium	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
118	152	Selye János Egészségügyi Technikum	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
119	153	Újpesti Bródy Imre Gimnázium és Általános Iskola	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
120	154	Újpesti Bródy Imre Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
121	155	Kőbányai Szent László Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
122	156	VSZC Boronkay György Műszaki Technikum és Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
123	157	Színyei Merse Pál Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
124	158	VSZC Boronkay György Műszaki Technikum és Gimnázium és Gimnázium, Vác	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
125	159	Varga István SZKI	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
126	160	Teleki László Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
127	162	BGSzC Varga István Közgazdasági Technikum	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
128	163	Teleki László Gimnázium	Gyömrő	2025-11-28 05:12:26	2025-11-28 05:12:26
129	164	Váci SzC Selye János Egészségügyi Technikum	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
130	165	Budapest XXII. Kerületi Budai Nagy Antal Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
131	166	BKSZC Kreatív és Kézművesipari Technikum	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
132	167	Egyéni	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
133	168	Rákóczi	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
134	169	I. Kerületi Petőfi Sándor Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
135	170	Budaörsi 1. Számú Általános Iskola	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
136	171	Budaörsi I kerületi általános iskola	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
137	172	Semmelweis Egyetem Raoul Wallenberg	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
138	173	Semmelweis Egyetem Raoul Wallenberg Többcélú Szakképző Intézménye	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
139	174	Budapest I. Kerületi Petőfi Sándor Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
140	175	Szentistvántelepi Általános Iskola	Budakalász	2025-11-28 05:12:26	2025-11-28 05:12:26
141	176	Kőbányai Janikovszky Éva Magyar-Angol Két Tanítási Nyelvű Általános Iskola	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
142	177	Petőfi Sándor Katolikus Általános Iskola és Óvodaa	Kecskemét	2025-11-28 05:12:26	2025-11-28 05:12:26
143	178	Budapest XVI. Kerületi Táncsics Mihály Általános Iskola és Gimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
144	179	Árpád Gimnázium/Tatabánya	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
145	180	Bernáth Kálmán Református Gimnázium	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
146	181	Budai Gimnázium és Szakgimnázium	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
147	182	Fazekas Mihály Gimnázium	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
148	183	Madách Imre Gimnázium, Vác	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
149	184	Nagy Sándor József Gimnázium	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
150	185	Táncsics Mihály Mezőgazdasági Technikum, Vác	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
151	186	Vak Bottyán Technikum, Gyöngyös	\N	2025-11-28 05:12:26	2025-11-28 05:12:26
152	187	AM KMASzC Táncsics Mihály Mezőgazdasági Technikum, Szakképző Iskola és Kollégium	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
153	188	Bernáth Kálmán Református Gimnázium	Vác	2025-11-28 05:12:26	2025-11-28 05:12:26
154	189	Budai Gimnázium és Szakgimnázium	Budapest	2025-11-28 05:12:26	2025-11-28 05:12:26
155	190	Tatabányai Árpád Gimnázium	Tatabánya	2025-11-28 05:12:26	2025-11-28 05:12:26
156	191	Árpád Gimnázium/Tatabánya	Tatabánya	2025-11-28 05:12:26	2025-11-28 05:12:26
\.


--
-- Data for Name: tablo_projects; Type: TABLE DATA; Schema: public; Owner: photo_stack
--

COPY public.tablo_projects (id, local_id, external_id, name, partner_id, status, is_aware, created_at, updated_at, data, sync_at, school_id, class_name, class_year) FROM stdin;
18	18	18	Boronkay György Műszaki Technikum és Gimnázium, Vác - 12 K 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "7a55436d-633c-4ab9-827f-d9f046566033", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 K", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "73", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	41	12 K	2026
32	32	32	Corvin Mátyás Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "3d48bd93-4108-4061-96c5-5086be33e740", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "10", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	9	12 A	2026
46	46	46	Könyves Kálmán Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "c624b21a-17cf-4bef-bf75-a52dfa55a0ac", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "136", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	102	12 A	2026
55	55	55	Váci Madách Imre Gimnázium - 12 C 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "4c016c33-1a4f-400c-8317-861e0317f9ed", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 C", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "64", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	32	12 C	2026
63	63	63	Nagy Sándor József Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "4d3af419-657f-4b15-bb64-ef537da3ca43", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "184", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	149	12 A	2026
16	16	16	Boronkay György Műszaki Technikum és Gimnázium, Vác - 12 E 2021 - 2026	1	not_started	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "df1086a8-b467-4296-809a-d33fe5aae335", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": "order/attachments/boronkay-gyorgy-muszaki-technikum-es-gimnazium-vac-2021-2026-12-e-background.jpg", "class_name": "12 E", "class_year": "2021 - 2026", "order_form": "order/form/boronkay-gyorgy-muszaki-techni-2021-2026-12-e-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>Helló sikerült döntenünk hogy milyen tablót szeretnénk ilyen koncert/fesztivál beütéssel. Találtunk ilyet a neten nagyon köszönnénk ha egy ilyet össze tudnál hozni. Ha kellenek információk szólj nekem és válaszolok.</p>", "font_family": "Grafikusra bízom", "old_school_id": "73", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Barnák Dominik\\r\\nCsapó Ádám András\\r\\nDemeter Gergő\\r\\nDóka Bálint\\r\\nEiler Ákos Levetne\\r\\nFarkas Verona Mária\\r\\nGyuricza Botond Balázs\\r\\nHangodi István Ábris\\r\\nHerczig Balázs\\r\\nHoó-Lantos Lilla\\r\\nHorváth Nándor\\r\\nJoó Bálint\\r\\nKocsis Tamás\\r\\nKorbely Bence\\r\\nKucsera Gergő\\r\\nKürti Kende\\r\\nLajcsok Hanga\\r\\nLehel Dániel\\r\\nMolnár Levente\\r\\nMolnár-Arany Naomi\\r\\nNagy Titusz\\r\\nRácz Gergő\\r\\nSoós Johanna\\r\\nSurányi Szilárd Koppány\\r\\nSzabó Krisztián Dezső\\r\\nSzeghalmi Csenge Lívia\\r\\nSzilner Botond\\r\\nSzűcs Enikő Sára\\r\\nUngár Máté Vazul\\r\\nUngár Sára Melinda\\r\\nVigh Bence", "teacher_description": "Fábián Gábor igazgató\\r\\nDian János igazgató-helyettes\\r\\nSzlobodnikné Kiss Edit igazgató-helyettes\\r\\nHorváth László igazgató-helyettes\\r\\nCzene Gábor osztályfőnök-történelem\\r\\nPalkó-Nagy Márta magyar nyelv és irodalom\\r\\nLustyik Ágnes matematika\\r\\nMocsári Nóra matematika\\r\\nSchofferné Szász Ildikó matematika és fizika\\r\\nHevér János testnevelés\\r\\nNagy Péter testnevelés\\r\\nBíró-Sturcz Anita biológia\\r\\nFaroun-Cserekly Éva angol\\r\\nRauscher István analóg áramkörök\\r\\nGyetván Károly digitális áramkörök\\r\\nPistyúr Zoltán PLC\\r\\nFabók Botond PLC\\r\\nBurger Balázs Péter elektronika gyakorlat\\r\\nBea Mónika Izabella angol\\r\\nNovák Mónika Zsuzsanna angol\\r\\nHolman Nóra testnevelés\\r\\nTóth Barnabás elektronika gyakorlat\\r\\nBermann Gábor gépészet gyakorlat\\r\\nMegyeri Balázs gépészet gyakorlat\\r\\nNiedermüllerné Karcag Ildikó\\r\\nNemes Tibor testnevelés\\r\\nSzabó Attila informatika\\r\\nBaksza Dávid informatika\\r\\nSzabó Zsombor János angol\\r\\nWachtler Viktor angol\\r\\nUrbán Andrea Judit angol\\r\\nDaróczi Éva angol\\r\\nSteiner Krisztina testnevelés\\r\\nKemenes Tamás programozás\\r\\nWiezl Csaba programozás\\r\\nMolnár Mária igazgató-helyettes"}	2025-11-26 13:55:08	41	12 E	2021 - 2026
57	57	57	Váci Madách Imre Gimnázium - 12. E 2026	1	waiting_for_photos	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "9c3ce34d-e408-423c-b8e4-c4bce5076d73", "color": "#000000", "quote": "még nem választottunk", "size_id": "5", "category": null, "sort_type": "mindenki befele nézzen", "background": null, "class_name": "12. E", "class_year": "2026", "order_form": "order/form/vaci-madach-imre-gimnazium-12-12-e-megrendelolap.pdf", "other_file": "order/attachments/vaci-madach-imre-gimnazium-12-12-e-otherfile.jpg", "ai_category": null, "description": "<p>egyszerű hagyományos tablót gondoltunk, tanárok felül, alatta diákok</p><p>háttér fehér vagy világos</p><p>ötlet, hogy a tablókép mellett kisebb formában egy gyerekkori kép is lenne mindenkiről</p><p>csatolok egy mintát</p>", "font_family": "Grafikusra bízom", "old_school_id": "64", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "1.\\tBraun Zsófia\\r\\n2.\\tCzifra Eszter\\r\\n3.\\tCsóri Zoé Hanna\\r\\n4.\\tÉrsek Nóra\\r\\n5.\\tFábián Kitti\\r\\n6.\\tFranyó Hanna\\r\\n7.\\tFriesz Anna\\r\\n8.\\tGáspár Anna\\r\\n9.\\tGólya Levente\\r\\n10.\\tGulyás Boróka\\r\\n11.\\tHriagyel Linetta\\r\\n12.\\tJenei Balázs\\r\\n13.\\tKatona Lara\\r\\n14.\\tKiss Boglárka\\r\\n15.\\tKiss Veronika\\r\\n16.\\tKis-Vén Veronika\\r\\n17.\\tMáté Ádám\\r\\n18.\\tMezei Virág\\r\\n19.\\tMuckstadt Zénó Sándor\\r\\n20.\\tOroszlány Réka\\r\\n21.\\tPálinkás Petra\\r\\n22.\\tPaupa Eszter\\r\\n23.\\tRichter Laura\\r\\n24.\\tRottenhoffer Anna Dóra\\r\\n25.\\tSikó Lilla Csillag\\r\\n26.\\tSzabó Virág\\r\\n27.\\tSzegner Amina Jázmin\\r\\n28.\\tSzó Enikő \\r\\n29.\\tTrudics Lara\\r\\n30.\\tVizler Bálint Levente\\r\\n31.\\tWhite Vince Péter", "teacher_description": "Steidl Levente ig.\\r\\nHorváth Edit igh.\\r\\nLakatos-Tombácz Ádám igh.\\r\\nKovácsné Kóka Marianna ofő\\r\\nBata Enikő\\r\\nBlahóné Vona Csilla\\r\\nDuhonyi Anita\\r\\nKardos József\\r\\nKenderessy Tibor\\r\\nMerész Henrietta\\r\\nMolnár Rita\\r\\nSajgó Emese\\r\\nStrenner Anita Heléna\\r\\nSzőke László\\r\\nUntsch Gergely Ádám"}	2025-11-05 13:31:25	32	12. E	2026
4	4	4	Tatabányai Árpád Gimnázium - 12. A 2020-2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "f994370c-5c1f-400f-961e-dbdf9117e4bf", "color": "#1f4898", "quote": "", "size_id": "5", "category": null, "sort_type": "mindenki befele nézzen", "background": null, "class_name": "12. A", "class_year": "2020-2026", "order_form": "order/form/tatabanyai-arpad-gimnazium-2020-2026-12-a-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>Fekvő tájolású tablót szeretnénk, amelyen szerepel az iskola neve, az osztály jelzése és a 2020-2026-os évszám.&nbsp;</p><p>A tanárokat kérjük szépen felül elhelyezni, az osztályfőnököt valahova a diákok közé beilleszteni. A tablóképek alá szeretnénk mindenkihez egy idézetet tenni - amennyiben ez megoldható -, a kép mellé pedig egy óvodás korban készült fotót szeretnénk. Utóbbival kapcsolatban az az elképzelés, hogy a képen szereplő személy alakja “kivágva” kerüljön a tablókép mellé.</p><p>A háttér legyen semleges, egyszínű.</p>", "font_family": "Grafikusra bízom", "old_school_id": "190", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Belinszki Loránd\\r\\nBukovics Péter\\r\\nCsernai Lili Anna\\r\\nCsernai Tibor Ármin\\r\\nDrahos Máté\\r\\nÉrchegyi Ágnes Luca\\r\\nFekete Zoltán András\\r\\nGábor Luca\\r\\nGordos Tamás\\r\\nGyurmánczi Milán\\r\\nHauszknecht Fanni\\r\\nHoffart-Schárfi Réka\\r\\nHuber Kristóf\\r\\nIvanov Zorka\\r\\nKakuk Mihály\\r\\nKalmár Kata Dorka\\r\\nKemény Veronika\\r\\nKontra Dávid Levente\\r\\nKovács Réka\\r\\nOcsenás Olívia Izabella\\r\\nPerenyei Noémi\\r\\nScheib Dominik\\r\\nSchmidt Evelin\\r\\nStraubinger Péter\\r\\nSztrikinácz Flóra Dorina\\r\\nTujfl Mirkó\\r\\nVarga Valéria\\r\\nVárpalotai Szilárd Viktor\\r\\nVörös Botond Gellért", "teacher_description": "Polyóka Tamás igazgató\\r\\nKovács Miklós nyugalmazott igazgató\\r\\nKántor Péter igazgatóhelyettes\\r\\nMagyari Gábor igazgatóhelyettes\\r\\nBekéné Kucsera Zsuzsanna\\r\\nBlaskó Ildikó\\r\\nDörnyei Szilvia\\r\\nErl Andrea \\r\\nFüzesi Zsanett\\r\\nHorváth Anikó\\r\\nJakab Zsuzsanna\\r\\nKatonáné Tímár Mária\\r\\nLábszkiné Tatai Ilona\\r\\nMagyariné Sárdinecz Zsuzsanna\\r\\nMolnár Adrienn\\r\\nSolymos Nóra\\r\\nSzalai Gizella\\r\\nSzalay Izabella Veronika\\r\\nSzeimann Zsuzsanna\\r\\nTara Andrea\\r\\nVida Matild osztályfőnök\\r\\nWéber Balázs\\r\\nZámbóné Borvendég Katalin\\r\\nZovitsné Aba Veronika\\r\\n\\r\\nTanáraink voltak még (fotó nélkül):\\r\\nDienesné Ablonczy Anna\\r\\nBalpatakiné Pintér Zsuzsanna\\r\\nBeckerné Neuberger Mariann\\r\\nBobek Márta\\r\\nFrech Erika\\r\\nHarmath Zoltánné\\r\\nLábszki József\\r\\nNémeth Ildikó\\r\\nMészáros Veronika\\r\\nPfiszterer Zsuzsanna"}	\N	155	12. A	2020-2026
74	74	74	Kőbányai Szent László Gimnázium - 12.a 2021-2026	1	waiting_for_photos	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "52965b0c-ec4e-4cfd-ab23-a3901a8584e8", "color": "#c9ccd3", "quote": "", "size_id": "5", "category": null, "sort_type": "mindenki befele nézzen", "background": "order/attachments/kobanyai-szent-laszlo-gimnazium-2021-2026-12a-background.jpeg", "class_name": "12.a", "class_year": "2021-2026", "order_form": "order/form/kobanyai-szent-laszlo-gimnaziu-2021-2026-12a-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>Arra gondoltunk, hogy minden diák tabló képének jobb oldalán a gyerekkori képe legyen.</p>", "font_family": "Grafikusra bízom", "old_school_id": "155", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Artner Noémi\\r\\nBadó Csenge\\r\\nBaran Bertalan\\r\\nBartucz Boglárka\\r\\nBata Orsolya\\r\\nBordás Péter\\r\\nBővíz Flóra\\r\\nDelea Márton \\r\\nDrei Izabell\\r\\nEisemann Zita\\r\\nFarkas Emma\\r\\nFerbert Flóra\\r\\nGavalovics Míra\\r\\nGörömbei Blanka\\r\\nHorváth Bálint\\r\\nHowle George Elliot\\r\\nKovácsi Sára Kata\\r\\nMáté-Steff Szíra\\r\\nMolnár Sophie\\r\\nNagy Lilla\\r\\nPedone Giulia \\r\\nPéger Dóra\\r\\nRaffay Kristóf\\r\\nRigó Emese Csilla\\r\\nRónaháty Boglárka\\r\\nSchmidtka Borbála\\r\\nStróbli Benjámin\\r\\nSzabó Laura\\r\\nSzabó Marcell Dénes\\r\\nSzalay Tamás\\r\\nTóbiás Levente\\r\\nTóth Boglárka Panna\\r\\nVíg Csenge\\r\\nZabó Lilla", "teacher_description": "Péteri Zsuzsanna\\r\\nKlabacsek Rita\\r\\nCsákvári Lili\\r\\nTomor Judit\\r\\nLissák Bertalan\\r\\nEletto Gianmaria Domenico\\r\\nDeák Ferenc\\r\\nNagy Szilvia\\r\\nSomkövi Bernadett\\r\\nBükkösi Hajnal\\r\\nFöldesi Dávid\\r\\nZanna Giuliana\\r\\nZákonyi Flóra\\r\\nMüller Ágnes\\r\\nSzendrei Péter\\r\\nMenyhárt Krisztina\\r\\nGyetvai Györgyi\\r\\nTeremy Krisztina\\r\\nVarga Bettina\\r\\nTandory Gábor\\r\\nDarabánt Emese\\r\\nOrbán Angelika\\r\\nKocsis Mariann\\r\\nNagy Katalin"}	2025-11-05 14:19:56	121	12.a	2021-2026
40	40	40	Illyés Gyula Gimnázium - 12 C 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "635eeb96-00cc-4cc4-a2a2-029defc425b5", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 C", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "118", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	85	12 C	2026
52	52	52	BKSZC Kreatív és Kézművesipari Technikum - 13 D 2021-2026	1	waiting_for_photos	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "c9ac714f-8ade-4b7c-81ee-a10f73e91faf", "color": "#ffffff", "quote": "", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": "order/attachments/bkszc-kreativ-es-kezmuvesipari-technikum-2021-2026-13-d-background.jpg", "class_name": "13 D", "class_year": "2021-2026", "order_form": "order/form/bkszc-kreativ-es-kezmuvesipari-2021-2026-13-d-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>Spotify-os tablót szeretnénk úgy hogy majd mindegyik diáknak a jobb sarkában ott van a kedvenc albuma. Középen nagyban a spotify logo de ezt már háttérképként csatoltam de nyugodtan lehet rajta változtatni. Amelyik tanárról nem kapnak tavalyi képet azoknak nem kell rajta lenni.</p>", "font_family": "Grafikusra bízom", "old_school_id": "166", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Albert Hanna\\r\\nBerta Nikoletta Boglárka\\r\\nBúzády Lídia\\r\\nBúzás Bence\\r\\nCsengeri Bíborka\\r\\nCsizmazia Vanda Opál\\r\\nEmődi-Varga Fruzsina\\r\\nHazenfratz Alexandra Gabriella\\r\\nHollós Lilla\\r\\nIbolya Zsanna Bettina\\r\\nKálmán Nóra Gabriella\\r\\nKreskai Rebeka\\r\\nLencsér Dalma\\r\\nMajtász-Susla Nelli\\r\\nMonori Fanni\\r\\nNagy Adrienn\\r\\nNagy Bettina Vivien\\r\\nNagy Janka Panka\\r\\nRadeczki Jázmin \\r\\nRitter Alexandra \\r\\nSallai Fruzsina\\r\\nSáska Natália\\r\\nSerényi Dorottya\\r\\nSzórádi Eszter\\r\\nÜrögi Lívia", "teacher_description": "Osztályfőnök: Grábits Ágota\\r\\nBassay Klára\\r\\nStark Tibor\\r\\nBoros Réka\\r\\nCsöke Olivér Péterné\\r\\nHajnalné Márta Anita\\r\\nKanics Márta\\r\\nKaszás Dóra \\r\\nMolnár Kinga\\r\\nOláh Anita\\r\\nSneff Szilárd\\r\\nSzóda Zsuzsanna\\r\\nKovácsné Piros Gizella \\r\\nIgazgatónő: Dóczi Krisztina\\r\\nIgazgatóhelyettesek: Kiszály E. Anna\\r\\nKolbása Mária\\r\\nGuzmann Katalin"}	2025-10-10 07:15:52	131	13 D	2021-2026
6	6	6	Árpád Gimnázium/Tatabánya - 12 C 2022-2026	1	needs_forwarding	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "3f828039-389b-44b6-b2c5-b339c8373a91", "color": "#ffaa02", "quote": "", "size_id": "5", "category": null, "sort_type": "mindenki befele nézzen", "background": null, "class_name": "12 C", "class_year": "2022-2026", "order_form": "order/form/arpad-gimnaziumtatabanya-2022-2026-12-c-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>A tabló háttere: RGB 31 11 74</p><p>A diákok tabló képe nagyobb, míg a tanárok tabló képe kisebb legyen, kivétel az osztályfőnök és a pótosztályfőnöké.</p><p>A diákok tabló képe alatt egy-egy idézet is szerepelne, amit majd később e-mailben küldenénk.</p>", "font_family": "Bingo Dilan", "old_school_id": "191", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Balaton Levente\\r\\nBan Ákos\\r\\nBaráth Zsombor\\r\\nBernvallner Gergő\\r\\nBorbáth István\\r\\nBrunner Liza\\r\\nBoda Helga\\r\\nErős Barnabás\\r\\nGaál Balázs\\r\\nGrásel Péter\\r\\nGroszkopf Titanilla\\r\\nHerpai Péter\\r\\nHorváth Levente\\r\\nIndi Olivér\\r\\nJuhász Ambrus\\r\\nKörtvélyfáy János\\r\\nKövesi Viktor\\r\\nLányi Lili\\r\\nMajoros Ádám\\r\\nMeszner Klaudia\\r\\nMoravcsik Nóra\\r\\nNagy Barnabás\\r\\nPapp Lóránt\\r\\nSolymár Dávid\\r\\nSzalczinger Anna\\r\\nTátrai Alíz\\r\\nTirhold Inez\\r\\nTóth Klaudia\\r\\nVarga Kincső\\r\\nVégh Vanessza\\r\\nVida Janka", "teacher_description": "Polyóka Tamás (Igazgató Úr)\\r\\nJakab Zsuzsanna (Pótosztályfőnök)\\r\\nMuszka Zoltán (Osztályfőnök)\\r\\nArnóczkyné Szabó Nóra\\r\\nGeiszt Ferenc Dezső\\r\\nGödri Krisztina\\r\\nMagyariné Sárdinecz Zsuzsanna\\r\\nNémeth Ildikó\\r\\nSzokoli Kinga\\r\\nHarmath Zoltánné\\r\\nZámbóné Borvendég Katalin\\r\\nSzalai Gizella\\r\\nPfiszterer Zsuzsanna\\r\\nLábszkiné Tatai Ilona\\r\\nBobek Márta\\r\\nDörnyei Szilvia Mária\\r\\nBakos Lilla Boglárka\\r\\nBekéné Kucsera Zsuzsanna\\r\\nHorváth Anikó\\r\\nKörtvélyfáy Attila"}	2025-11-21 15:58:45	156	12 C	2022-2026
5	5	5	Tatabányai Árpád Gimnázium - 12.B 2022-2026	1	not_started	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "c530f112-0a52-4c76-9278-bdfdd6c4b39c", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": "order/attachments/tatabanyai-arpad-gimnazium-2022-2026-12b-background.jpg", "class_name": "12.B", "class_year": "2022-2026", "order_form": "order/form/tatabanyai-arpad-gimnazium-2022-2026-12b-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>Az osztályunk Vogue témájú tablót szeretne. A Vogue főoldaláról a cikkek lennének a tablófotóink, mindenkinek a cikkcím helyén lenne egy rövid idézete. Szeretnénk valahol egy QR-kódot is elhelyezni, aminek beolvasásán keresztül meglehet majd nézni a szalagavatós filmünket. Csatolok képet az elképzeléshez, de nyugodtan (sőt kérem) nézzenek rá a Vogue oldalára is ötletekért.</p>", "font_family": "A Vogue oldal jellegzetes betűtípusa", "old_school_id": "190", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "András Gergő Hunor \\r\\nBánóczki Viktor \\r\\nBaranyai Dorina Réka \\r\\nBaranyai Tímea \\r\\nBodnár Nóra \\r\\nBokodi Szilvia \\r\\nDankó Zoltán Kristóf \\r\\nFekete Kata Hanna \\r\\nFoki Regina Zoé \\r\\nHavas Tamás \\r\\nHeringer Péter \\r\\nHorváth Patrik \\r\\nJózsi Petra Boglárka \\r\\nKéri-Zsigmond Kata \\r\\nKis-Prumik Csenge Boglárka \\r\\nKiss Renáta Ramóna \\r\\nKoósz Olívia Henrietta \\r\\nKozicz Júlia Anna \\r\\nLászló Réka \\r\\nLendvai Kamilla \\r\\nMakay Kamilla \\r\\nMolnár Ádám János \\r\\nNagy Mátyás \\r\\nNémeth Lili \\r\\nNovák Dorka \\r\\nPruzsina Patrícia \\r\\nSántha Hanga Menta \\r\\nSimon Boglárka Anna \\r\\nSpóner Jázmin \\r\\nSzépfi Hanna Flóra \\r\\nSzommer Alíz\\r\\nTörök Judith Dalma \\r\\nVarga Roberta \\r\\nWachter Eliza Anita", "teacher_description": "Tanárok, akik felkerülnek képpel:\\r\\nPolyóka Tamás (igazgató) \\r\\nKántor Péter (igazgatóhelyettes) \\r\\nMagyari Gábor (igazgatóhelyettes)\\r\\nGulyás Tamás (ofő) \\r\\nSzalay Izabella (ofő) \\r\\nBarkó Orsolya \\r\\nFazekas József \\r\\nLábszki József \\r\\nBobek Márta \\r\\nJakab Zsuzsanna \\r\\nBakos Lilla Boglárka \\r\\nBekéné Kucsera Zsuzsanna \\r\\nSzokoli Kinga \\r\\nErl Andrea \\r\\nHorváth Anikó \\r\\nJózan Péter \\r\\nKatonáné Tímár Mária \\r\\nLábszkiné Tatai Ilona \\r\\nMagyariné Sárdinecz Zsuzsanna \\r\\nMózesné Vincze Jolán \\r\\nSzalai Gizella \\r\\nSzeimann Zsuzsanna \\r\\nTara Andrea \\r\\nVadné Vankó Alíz \\r\\nWéber Balázs \\r\\nDörnyei Szilvia Mária\\r\\n\\r\\nKép nélkül, csak név szerint megemlített tanárok:\\r\\nCsicsai Kata, Kovácsné Schandl Ágnes, Frech Erika, Muraközy János Gábor, Dienesné Ablonczy Anna, Németh Zsófia, Zovitsné Aba Veronika, Geiszt Ferenc Dezső, Gergőné Szőlősi Tünde, Gödri Krisztina, Polyókáné Hurai Hedvig, Póczos Tamás, Schweininger Anita"}	2025-11-21 15:44:14	155	12.B	2022-2026
13	13	13	Bernáth Kálmán Református Gimnázium - 13 2 2026	1	waiting_for_photos	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "fed48194-7d02-4c77-86fe-3ef4487efba7", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": "order/attachments/bernath-kalman-reformatus-gimnazium-2026-13-2-background.jpeg", "class_name": "13 2", "class_year": "2026", "order_form": "order/form/bernath-kalman-reformatus-gimn-2026-13-2-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>&lt;link rel=\\\\\\"preconnect\\\\\\" href=\\\\\\"https://fonts.googleapis.com\\\\\\"&gt;<br>&lt;link rel=\\\\\\"preconnect\\\\\\" href=\\\\\\"https://fonts.gstatic.com\\\\\\" crossorigin&gt;<br>&lt;link href=\\\\\\"https://fonts.googleapis.com/css2?family=Lobster+Two:ital,wght@0,400;0,700;1,400;1,700&amp;display=swap\\\\\\" rel=\\\\\\"stylesheet\\\\\\"&gt;<br>Ezt a betű típust szeretnénk&nbsp;</p><p>Nem tudtam sajnos a tablónk hátterét be illeszteni ezért csak a kész tablót ílesztetem be de ha a sima hátére is szükséglene akkor kérem írjon egy emailt és ott el küldöm önnek.</p><p>&nbsp;A kész tablonkon minden ott van ahogyan szeretnénk az egészet és a link amit be ílesztetem azzal a betű típussal szeretnénk kérni&nbsp;</p><p>&nbsp;</p>", "font_family": "Grafikusra bízom", "old_school_id": "188", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Balga Krisztián \\r\\nBecsei Balázs \\r\\nBotos Zsófia\\r\\nCsancsár Máté Levente \\r\\nCsata Bogi\\r\\nFűzik Dániel\\r\\nJuhász Gabriella\\r\\nKis Kiara Alexandra\\r\\nKovács Adrien\\r\\nMagyar Zoltán Csongor\\r\\nMaróti Ramóna Liza \\r\\nMitasz Laura Réka\\r\\nNagy Alma\\r\\nNagy Renáta\\r\\nSzabados Zalán Nimród\\r\\nValentics Kamilla Flóra\\r\\nVarga Vanda\\r\\nVarga Vanessza\\r\\nZachar Zita", "teacher_description": "Csak a nevek:\\r\\nFábián Tamás\\r\\nVarga Zsuzsanna\\r\\nBócz Mónika\\r\\nPapp Zoltán\\r\\nDarida Katalin\\r\\nBognár Ákos\\r\\nBajnokné Muszkalay Tünde\\r\\nZseni István Béla\\r\\nBollog Melinda\\r\\nHargitai György\\r\\nBaginé Örsi Orsolya\\r\\nNyári Bálint\\r\\nSzabó Ferenc\\r\\nSzenci Krisztina\\r\\nStorecz  József\\r\\nFotók a tanárokról:\\r\\nKincses Katalin\\r\\nHorváth Antal\\r\\nTörök Anna Hajnalka\\r\\nKereskényi Balázs\\r\\nKenéz Anita\\r\\nMarján Ibolya\\r\\nDr Markóczi Mária\\r\\nKovács Ákos\\r\\nSzilágyiné Bubenka Ildikó Erika\\r\\nRácz Róbert \\r\\nNémeth Éva (igazgatóhelyettes)\\r\\nKálnainé Gyarmati Klára (igazgatóhelyettes)\\r\\nSólyomsi Csilla (Igazgató)\\r\\nMenczler Ágnes (igazgatóhelyettes)\\r\\nTörök István János"}	2025-10-26 08:51:34	153	13 2	2026
17	17	17	Boronkay György Műszaki Technikum és Gimnázium, Vác - 12 I 2026	1	waiting_for_response	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "7b6a7d46-b129-4cab-8578-b74de6edf5d2", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": null, "class_name": "12 I", "class_year": "2026", "order_form": "order/form/boronkay-gyorgy-muszaki-techni-2026-12-i-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>Teljesen alapvető, semmi kifejezett témára nem hajtó tablót szeretnénk az osztály döntése alapján. Sötét hátterű, rajta a diákok és tanár egyaránt szimmetrikusan elhelyezve, mindenki a tabló közepe fele nézzen.</p><p>A tanárok némelyikét csak szövegesen szeretnénk felrakni a tablóra, ezt gondolom utólagos egyeztetéssel kell majd jeleznünk. A tanárokat megpróbálta az osztály összegyűjteni, de elképzelhető, hogy kimaradt valaki, illetve a vezetőség tagjai nincsenek fent a listában.</p>", "font_family": "Grafikusra bízom", "old_school_id": "73", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Abos Péter, Agócs Gergely Botond, Balogh Gergő Botond, Baradlai Patrik Ferenc, Bayer Bálint, Béki Márton, Boros Jonatán, Czinege Márton, Drajkó Gergő, Erdővölgyi Bendegúz, Érsek Huba, Farkas Áron, György Zoltán Szilárd, Hajtó Lili, Hekli Bence, Horlik Nimród Imre, Horváth Márk, Kövesdi Márk, Nádasi Gergő, Okolenszki Zalán, Ondrik Barnabás, Oszaczki Csaba, Ozsváth Bendegúz Máté, Radics Ádám, Selmeczy György, Simák Balázs István, Szabó Balázs Sámuel, Szalay Máté, Szántó Dávid, Törő Marcell, Veres Milán", "teacher_description": "Kemenes Tamás\\r\\nWiezl Csaba\\r\\nKovács László\\r\\nVéglesiné Bíró Erzsébet\\r\\nTrieb Márton\\r\\nVáradi Ágnes\\r\\nFoltán Zoltán\\r\\nSomoskői Balázs Donát\\r\\nPistyúr Zoltán\\r\\nSzász Csilla\\r\\nVirág György\\r\\nSzabó Attila\\r\\nOrgoványi József\\r\\nVidra András\\r\\nBaksza Dávid\\r\\nBlaha Péter"}	2025-11-05 04:48:23	41	12 I	2026
22	22	22	Újpesti Bródy Imre Gimnázium - 12 DN 2022-2026	1	waiting_for_photos	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "9b050bfa-c3f2-430a-ae19-203aaf37eaaa", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": null, "class_name": "12 DN", "class_year": "2022-2026", "order_form": "order/form/ujpesti-brody-imre-gimnazium-2022-2026-12-dn-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>A tablót egy semleges fehér háttérre kérjük, szeretnénk kérni, hogy a kész képet fájl formában küldje el nekünk az <a href=\\\\\\"mailto:emmavillanyi@gmail.com\\\\\\">emmavillanyi@gmail.com</a> emailcímre.&nbsp;</p>", "font_family": "Grafikusra bízom", "old_school_id": "154", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Buti Izabella\\r\\nFricsfalusi Zsuzsanna\\r\\nHalász Vivien Vanda\\r\\nKárpáti Bálint\\r\\nKollár Dávid\\r\\nKoncz Gabriella Petra\\r\\nKószó Álmos Levente\\r\\nKürtösi Hanna Cintia\\r\\nKurucz András\\r\\nLukács Kata\\r\\nMolnár Angéla \\r\\nNagy Brigitta\\r\\nNagy Dóra\\r\\nSallai Carlos\\r\\nSoós Ádám\\r\\nTahon Larina\\r\\nTizedes Hanna\\r\\nVillányi Emma\\r\\nVizin Vanda", "teacher_description": "Ilauszkyné Varga Enikő - főigazgató\\r\\nBécsi Szilvia - főigazgató helyettes \\r\\nKállay Katalin - főigazgató helyettes\\r\\nLiszonyi Gábor - főigazgató helyettes \\r\\nBartha Gábor - média\\r\\nHuszti Lajos - matek\\r\\nHusztiné Varga Klára - orosz\\r\\nIstók Balázs - tesi\\r\\nKarawaj Ágnes - magyar\\r\\nSütőné Seres Adrienn - dráma\\r\\nSzőlősi Krisztina - angol\\r\\nSztaskó Richárd - színtöri\\r\\nTatorján Dorottya - angol\\r\\nTimkóné Szatmár Éva - ofő\\r\\nValló Gábor - töri\\r\\nHorvath Ibolya - fizika\\r\\nMarcali Etelka - angol\\r\\nGaál Tamara - digitális kultúra\\r\\nKérdő Krisztina - tesi\\r\\nAmbrusné Berencz Zsuzsanna - biológia, kémia\\r\\nTóth Andrea - földrajz\\r\\nSzilágyi Anna- digitális kultúra\\r\\nSchiller Ágnes- biológia\\r\\nSzoboszlai Éva - vizuális kultúra"}	2025-10-25 12:52:46	120	12 DN	2022-2026
54	54	54	Váci Madách Imre Gimnázium - 12 B 2026	1	waiting_for_photos	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "b1e851ff-afbb-4e3e-949d-da2eee4e2958", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": null, "class_name": "12 B", "class_year": "2026", "order_form": "order/form/vaci-madach-imre-gimnazium-2026-12-b-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>Egy olyan tablót szeretnénk aminek egyszerü háttere lenne, és minden tanuló egy fikcionális karakterrel kifejezhetné magát ami a saját tabló fotója mellett lenne<br>(elnézést hogy ezt egy konkrét hónap késéssel adom le)</p>", "font_family": "Grafikusra bízom", "old_school_id": "64", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Andrényi Cintia\\r\\nBerki Áron\\r\\nBethlen Áron\\r\\nBodor Péter\\r\\nBurján Hajnalka\\r\\nCsontos Róbert\\r\\nDomján Zsigmond\\r\\nFiló Eszter\\r\\nForgó Vince\\r\\nGőgh Dóra\\r\\nHegedűs Dóra\\r\\nHorváth Lóránt\\r\\nHorvát Konrád Panna\\r\\nKálmán Zsombor\\r\\nKántor Lilla\\r\\nKis Dudás Vajk\\r\\nKovács Barnabás\\r\\nKovács Patrik\\r\\nKristóf Fanni\\r\\nKummer Bálint\\r\\nLukács Leila Zsófia\\r\\nMajer Blanka\\r\\nMáté Petra\\r\\nMészáros Luca\\r\\nNémeth Dengzik Csaba\\r\\nNiedermüller Anna\\r\\nSomloi Dominik\\r\\nSomlyai Botond\\r\\nTamara Bernadett Tóth\\r\\nTódor Tamás\\r\\nVillányi Károly Bendegúz", "teacher_description": "Benicsek Mihály\\r\\nBoda Mária\\r\\nFockter Zoltán\\r\\nHorváthné Strommer Éva\\r\\nKenderessy Tibor\\r\\nKuruczné Vágási Szilvia\\r\\nLámfalusi Réka\\r\\nPéntek Attiláné\\r\\nStrenner Anita\\r\\nTóth-Szabó Júlia"}	2025-10-25 14:37:44	32	12 B	2026
98	9999	99999	Teszt Iskola 12. A 2026	3	waiting_for_response	f	2025-11-30 14:59:00	2025-11-30 20:59:24	\N	\N	23	12. A	2026
76	76	76	AM KMASzC Táncsics Mihály Mezőgazdasági Technikum, Szakképző Iskola és Kollégium - 13.A 2026	1	waiting_for_photos	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "9f60290c-830a-45bb-b686-1da4d96f307b", "color": "#ffffff", "quote": "", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": "order/attachments/am-kmaszc-tancsics-mihaly-mezogazdasagi-technikum-szakkepzo-iskola-es-kollegium-2026-13a-background.jpeg", "class_name": "13.A", "class_year": "2026", "order_form": "order/form/am-kmaszc-tancsics-mihaly-mezo-2026-13a-megrendelolap.pdf", "other_file": "order/attachments/am-kmaszc-tancsics-mihaly-mezogazdasagi-technikum-szakkepzo-iskola-es-kollegium-2026-13a-otherfile.zip", "ai_category": null, "description": "<p>Az osztalyfőnökök és a diákok képei mellett mindenkinek egy-egy figura lenne. (Elvileg minden figuránál ott van a hozzá tartozó ember neve a csatolmányban)</p><p>A hátterét is változtatnánk egy picit ha lehet, a “keretben” lévő szívek helyett más alakzatot vagy akár üres helyet (szívek nélkül) szeretnénk.</p><p>A tabló tetején lenne:</p><p>-Jung Lilla igazgató (középen)&nbsp;</p><p>-Ihász István nevelési igazgatóhelyettes (igazgatónő mellett jobbra)&nbsp;</p><p>-Furcsa Gábor szakmai igazgatóhelyettes &nbsp;(igazgatónő mellett balra)</p><p>Mellettük szintén a tabló tetején a kedvenc tanáraink:</p><p>-Gyombolai Gyula<br>-Borosné Felföldi Mária Judit<br>-Bakos Ferenc Andreász<br>-Komlósi Attila<br>-Bakura József<br>-Vörös Ildikó<br>-Schwartz Marianna<br>-Jung Tímea<br>-Scherrenberg Johannes<br>-Rostás Balázs</p><p>Alattuk de középen lenne a 3 osztályfőnökünk a figuráikkal időrendi sorrendben:&nbsp;</p><p>1., Medgyesi-Lázár Mónika 2021-2023</p><p>2., Dudok Dávid 2023-2025</p><p>3., Elbakour Emíra 2025-2026</p><p>Illetve valahova szeretnénk még a többi tanárt is feltenni de csak névvel kép nélkül.</p><p>Tanítottak még:<br>Szalay Péter<br>Adorján Kinga<br>Böröcz Zsuzsanna<br>Csukáné Bozsok Gabriella<br>Ifjú Tamás<br>Küstel Richárd<br>Mészáros Sebestyén<br>Amy O’Brien<br>Petrezsél Tibor<br>Török Luca<br>Inotay Zsombor &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;Kollégiumi nevelő: Zelinka Éva</p><p>A tanulók elrendezése a grafikusra van bízva.&nbsp;<br>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p>", "font_family": "Oleo Script", "old_school_id": "187", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Balázs Gemma\\r\\nBertók Lili\\r\\nBódi Laura\\r\\nBokor Henrik\\r\\nCsizmadia Emese\\r\\nDancsevics Dominik\\r\\nDaub Nadine Rebeka\\r\\nDávid Kamilla\\r\\nDemeter Barbara\\r\\nHerendi Csenge\\r\\nHerczeg Flóra\\r\\nHorváth Boglárka\\r\\nKovács Nóra Emília\\r\\nKővári Csenge\\r\\nNagy Benedek Domonkos\\r\\nNagy Boglárka Boróka\\r\\nNagy Petra\\r\\nOrsik Dóra Natasa\\r\\nPallagi Katalin\\r\\nSarankó Bernadett\\r\\nSinger Szimonetta\\r\\nSzabó Dorina\\r\\nSzűcs Leila\\r\\nUdvarhelyi Luca\\r\\nUrbán Dorián\\r\\nVágó Zsófia\\r\\nVaisz Bendegúz\\r\\nVarga Eszter\\r\\nVecsei Boróka", "teacher_description": "Akiknek kép is lesz:\\r\\nBakos Ferenc Andreász\\r\\nBakura József\\r\\nBorosné Felföldi Mária Judit\\r\\nGyombolai Gyula\\r\\nJung Tímea\\r\\nKomlósi Attila\\r\\nRostás Balázs\\r\\nScherrenberg Johannes\\r\\nSchwartz Marianna\\r\\nVörös Ildikó\\r\\n\\r\\nAkik csak névvel szerepelnek:\\r\\n\\r\\nAdorján Kinga\\r\\nAmy O’Brien\\r\\nBöröcz Zsuzsanna\\r\\nCsukáné Bozsok Gabriella\\r\\nIfjú Tamás\\r\\nInotay Zsombor\\r\\nKüstel Richárd\\r\\nMészáros Sebestyén\\r\\nPetrezsél Tibor\\r\\nSzalay Péter\\r\\nTörök Luca\\r\\nZelinka Éva"}	2025-09-25 04:36:10	152	13.A	2026
75	75	75	Kőbányai Szent László Gimnázium - 12 D 2022-2026	1	waiting_for_response	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "1388ef74-53df-4940-b611-0518471e9c9d", "color": "#563d00", "quote": "", "size_id": "5", "category": null, "sort_type": "megjegyzésben jelöljük", "background": "order/attachments/kobanyai-szent-laszlo-gimnazium-2022-2026-12-d-background.jpeg", "class_name": "12 D", "class_year": "2022-2026", "order_form": "order/form/kobanyai-szent-laszlo-gimnaziu-2022-2026-12-d-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>E-mailben mellékelem a tablónk vázlatát, valamiért nem tudom beilleszteni az itteni mezőbe. Az alap koncepció, hogy mindenki kiválasztott egy magához illő figurát. A tanulók és a tanárok elrendezését is elküldöm egy e-mailben. Az első tizenkét tanárt csak névvel, a többit névvel és képpel ellátva szeretnénk feltüntetni, tantárgyaik nem szükségesek. A háttérkép ez a papír lenne, a figurák pedig, mintha matricák lennének, vékony fehér szegéllyel, hogy kitűnjenek a tablón, jobb alsó sarokba helyeznénk őket. A kis képkockáknak nem szeretnénk keretet. A betűszín mindenképp sötétebb legyen, a grafikusra bízzuk alapjáraton, de csatoltam egy barna árnyalatot. Ha bármilyen más infó kimaradt, nyugodtan szóljanak. A vázlatot szeretnénk majd a későbbiekben megkapni, ha esetleg bármiféle változtatásunk lenne.</p>", "font_family": "Grafikusra bízom", "old_school_id": "155", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Bildné Molnár Ágnes\\r\\nVarga Csilla\\r\\nBardóczky Anna \\r\\nFuriák Gergő\\r\\nVass Erika\\r\\nMészáros Edit \\r\\nSéra Rózsa\\r\\nMenyhárt Krisztina\\r\\nKuruczné Horvát Gabriella\\r\\nSóskuthyné Kovács Erzsébet\\r\\nGál Anikó\\r\\nNagy Katalin\\r\\n\\r\\nFöldesi Dávid\\r\\nKneusel Szilvia\\r\\nWill Dickerson\\r\\nHalász Judit \\r\\nRábai János \\r\\nBalanyi Rita\\r\\nPók Tímea\\r\\nTeremy Krisztina\\r\\nBajzáth Adrienn\\r\\nDeák Ferenc\\r\\nSzabados Péter\\r\\nKrasnyánszki Dóra \\r\\nNagy Mónika\\r\\nNémeth Szilvia \\r\\nGrund Ágnes\\r\\nNagy Bendegúz\\r\\nTandory Gábor\\r\\nBartháné Ábrahám Katalin\\r\\nHujber Szabolcs\\r\\nOrbán Angelika\\r\\nFekete Richard\\r\\nFodor Sára\\r\\nAudrey Déry\\r\\nLovas Erika\\r\\nDarabánt Emese\\r\\nSzolyka Alina Éva\\r\\nAdamis Bence \\r\\nPéteri Zsuzsanna\\r\\nSzendrei Péter\\r\\nLissák Bertalan", "teacher_description": "Azurák Hanna\\r\\nBalázs-Nagy Zsófia\\r\\nBalogh Dorka\\r\\nBárdos Janka\\r\\nBaumann Csaba Bálint\\r\\nBerecz Ákos\\r\\nDankó Noémi Kriszta\\r\\nDavini Sofia\\r\\nDézsi Viktória Csenge\\r\\nDudás-Györki Csenge\\r\\nGrandpierre Krisztián\\r\\nGulyás Liliána Olívia\\r\\nGyimesi Eszter Jázmin\\r\\nHeimpold Eliza Gréta\\r\\nKollár Milán\\r\\nKoppány Csenge\\r\\nKöteles-Hompoth Hunor\\r\\nLautner Erik\\r\\nMagyar Antónia\\r\\nMandl Edina Maja\\r\\nNagy Nóra Emília\\r\\nNagy Nóra Eszter\\r\\nNemes Gerda\\r\\nNossack Martin\\r\\nNyitrai Nóra Zsuzsanna\\r\\nPénzes Panna Virág\\r\\nShen Xin Lei\\r\\nSimon Anna Júlia\\r\\nSomodi Lili\\r\\nSzabó Örs Áron\\r\\nSzékely Imola\\r\\nSzőke Anna Sára\\r\\nTóth Botond\\r\\nTruong Ngoc Vianh\\r\\nVarga Boróka\\r\\nVarga Kristóf Levente\\r\\nVavrik Rella\\r\\nVégh Zsófia\\r\\nVörös Klára\\r\\nWong Ting Yi"}	2025-09-26 05:11:39	121	12 D	2022-2026
1	1	1	Árpád Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "ee284254-8bf2-4bde-adc4-40cce6e59dba", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "57", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	25	12 A	2026
2	2	2	Árpád Gimnázium - 12 B 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "267a5b6c-209d-471c-95e9-b8bd5f7b73cb", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 B", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "57", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	25	12 B	2026
3	3	3	Árpád Gimnázium - 12 C 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "ea9c2bcb-5088-4b0d-adee-9accb6813109", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 C", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "57", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	25	12 C	2026
9	9	9	Babits Mihály Gimnázium - 12 B 2026	1	waiting_for_photos	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "73e0b38e-3aae-4cec-8f2c-e466e93d695c", "color": "#ffffff", "quote": "", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": null, "class_name": "12 B", "class_year": "2026", "order_form": "order/form/babits-mihaly-gimnazium-2026-12-b-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>ChatGPT&nbsp;tematika</p><p>A tabló a ChatGPT kezelőfelületét mutatja be fekete háttérrel. A beszélgetés helyét a diákok fotói töltik ki (jobb oldalon és középen). Egyenként a fényképek alatt különböző kérdések vannak a ChatGPT&nbsp;felé buborékban, amit a diák tesz fel. A tanulónkénti kérdést fogjuk később küldeni.&nbsp;</p><p>Az egész felett van egy fő kérdés: “2018-2026 között mit kérdeztek leggyakrabban a diákok?”&nbsp;<br>&nbsp;</p><p>Válasz: “Ime, a diákok leggyakoribb kérdései:”&nbsp;</p><p>A kezelőfelületen a&nbsp; ChatGPT-nél van egy bal oldalsó sáv, ahol az előző beszélgetések vannak. Ott lennének a tanárok képei, nevei, tantárgyai. A tanároknál nincs kérdés, ők az előzmények.</p><p><br>&nbsp;</p>", "font_family": "Grafikusra bízom", "old_school_id": "3", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Alt Kornélia\\r\\nAsztalos Tamás\\r\\nBobák Mirabella\\r\\nBukovinszky Ádám\\r\\nBurcsa Anna Amira\\r\\nCsapó Nóra \\r\\nDihn Quoc Tung Tamás\\r\\nFazekas Panni\\r\\nHaraszti Krisztián\\r\\nKassai Csaba\\r\\nLázár Dávid \\r\\nMatics Marcell\\r\\nMihailov Klára\\r\\nNagy Ambrus\\r\\nNémeth Kristóf \\r\\nPerjési Balázs \\r\\nSrancsik Nóra \\r\\nSüdi Katalin\\r\\nSzepesi Boróka \\r\\nSzékács Laura Hanna\\r\\nTill Petra Réka \\r\\nVizi Lilla", "teacher_description": "Zsigriné Zeller Terézia (osztályfőnök, matematika)\\r\\nKaposiné Bodó Anna (magyar nyelv és irodalom) \\r\\nPapné Honti Mária (német nyelv) \\r\\nNémeth Máté (történelem) \\r\\nGyimesiné Kramer Judit (testnevelés) \\r\\nKósa Edit (vizuális kultúra) \\r\\nCsóka Beáta (ének-zene) \\r\\nOrosz Ágnes (földrajz) \\r\\nBodó Antalné (biológia) \\r\\nDr. Penyigei Erzsébet\\r\\nAnnusné Labancz Márta (német nyelv) \\r\\nKovácsné Ungár Tímea (spanyol nyelv) \\r\\nWiedemann Krisztina (angol nyelv) \\r\\nBalog Rita (francia nyelv) \\r\\nSuskó-Csécsi Petra (angol nyelv) \\r\\nSoha József (történelem) \\r\\nLohn Richárd (földrajz) \\r\\nMarton Sándor (fizika) \\r\\nLisztóczki János (testnevelés) \\r\\nLunczer Ildikó (matematika) \\r\\nRab Dóra (digitális kultúra) \\r\\nSóstói Gáborné (igazgatóhelyettes) \\r\\nPataki Marianna (igazgató) \\r\\nFucskó Anna (igazgatóhelyettes)"}	2025-09-25 13:23:50	2	12 B	2026
14	14	14	Bernáth Kálmán Református Gimnázium - 13/4 2026	1	waiting_for_photos	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "f1b4ba25-c19d-46d7-8dba-15be1407951d", "color": "#000000", "quote": ",,Élj úgy, hogy emléket hagyj.\\\\\\"", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": "order/attachments/bernath-kalman-reformatus-gimnazium-2026-134-background.jpg", "class_name": "13/4", "class_year": "2026", "order_form": "order/form/bernath-kalman-reformatus-gimn-2026-134-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>Ilyesmit képzeltünk el csak logisztikával kapcsolatosan. A függvény helyett repülő vagy vonat.</p><p>Világos színű háttér legyen.&nbsp;</p>", "font_family": "Grafikusra bízom", "old_school_id": "188", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Altsach Ákos\\r\\nAndruskó Csenge Anna\\r\\nBertalan Richárd\\r\\nCzoch Levente András\\r\\nDósa Benedek\\r\\nErdei Viktória\\r\\nErdélyi Noel Krisztián\\r\\nGál Benjámin\\r\\nGergely Janka\\r\\nKollár Milán\\r\\nKovács Viktória\\r\\nKrupa Szabolcs Lehel\\r\\nKukel Flóra\\r\\nLieszkovszki Dávid\\r\\nPapp Noémi Lilla\\r\\nPető Dominik Martin\\r\\nRévész Hanna\\r\\nSimkó Máté\\r\\nSzedlár Márk\\r\\nTakács Boglárka Zsófia\\r\\nUhlár Márkus\\r\\nVincze Róbert\\r\\nWeiger Valentin", "teacher_description": "Menczler Ágnes osztályfőnök \\r\\nSolymosi Csilla igazgatónő \\r\\nKálnainé  Gyarmati Klára intézmény egység vezető\\r\\nGyügyei Katalin\\r\\nMojzsis Andrea \\r\\nWachler Viktor\\r\\nVollai Dóra \\r\\nKonopás Attila\\r\\nOszaczkiné Szammer Beáta\\r\\nBócz Mónika\\r\\nKálmán Nóra\\r\\nSzemán Marietta\\r\\nBorsányi Iván"}	2025-10-10 07:58:28	153	13/4	2026
7	7	7	Árpád Gimnázium/Tatabánya - 12 D 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "bfc8c26f-662d-41c6-a360-d3f387d6e069", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 D", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "179", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	144	12 D	2026
8	8	8	Babits Mihály Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "4183a327-0174-4cbd-9633-f23051b6872f", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "3", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	2	12 A	2026
10	10	10	Babits Mihály Gimnázium - 12 C 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "88d2b90c-a0bf-43df-94da-839a9a8abf71", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 C", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "3", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	2	12 C	2026
11	11	11	Babits Mihály Gimnázium - 12 D 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "1231e4d3-e458-43ab-9792-1a6fc504ab9e", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": "order/attachments/babits-mihaly-gimnazium-2026-12-d-background.jpeg", "class_name": "12 D", "class_year": "2026", "order_form": "order/form/babits-mihaly-gimnazium-2026-12-d-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>A tablóval kapcsolatban arra jutottunk hogy marad a Batmanes téma, ez a kép az ötlet/inspiraci.</p><p><br>&nbsp;</p><p>Ezen kívül elrendezés szempontjából alulra kerülnének a tanárok, mellettük sarokba akiket kép nélkül szövegesen említünk meg. Fent a diákok ilyen felkör alakba illetve az osztályfőnök és osztályfőnök helyettes Batman két oldalára kerülnének. Szögletesesek lennének a képek alattuk pedig az általunk kivalasztott zene lenne de nem tudjuk egy mennyite férne rá. Spotify kóddal vagy a zene címével es egy kivagott résszel szerepelne. A képek sarkába DC-vel kapcsolatos kis képet mindenki választana. Ez mennyire működhet? A Tabló tetején szerepelni a 2022-2026</p>", "font_family": "Grafikusra bízom", "old_school_id": "3", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "BERTA BÁLINT\\r\\nBODOR LEVENTE\\r\\nCSIKI BARNABÁS\\r\\nCSIPAI GERGELY MÁRK\\r\\nDEÁK MARCELL\\r\\nDOBOS DORIÁN\\r\\nELŐD BOLDIZSÁR\\r\\nERTINGER ÁDÁM\\r\\nFARKAS RÉKA CSILLA\\r\\nFURÁK PÉTER\\r\\nILYÉS CSENGE LILI\\r\\nIMRAN AISHA\\r\\nKARÁCSONI ÁBEL\\r\\nKARÁCSONI CSONGOR\\r\\nKISS KITTI KIARA\\r\\nKÖRMÖCI CSENGE\\r\\nMARKOVICS FANNI\\r\\nMARKOVICS NOÉMI\\r\\nMOLNÁR GÁBOR DÉNES\\r\\nMOLNÁR SOMA\\r\\nNAGY MÁRK KRISZTIÁN\\r\\nNAGY-GRÓNER LUCA\\r\\nPODHORÁNYI MARCELL\\r\\nPUSZTAI LILIEN\\r\\nRESS LILI\\r\\nSZAKONYI LILLA JÚLIA\\r\\nSZÉKELY GRÉTA ESZTER\\r\\nTIMKÓ LÉNA\\r\\nTOLVAJ BENCE\\r\\nVERES RÉKA\\r\\nWINKLER FÜLÖP SÁNDOR", "teacher_description": "Képpel:\\r\\nGoda Lilla Boróka \\r\\nKocsis Éva Ildikó\\r\\nMartonné Czemel Katalin\\r\\nLakihegyi György\\r\\nZsigriné Zeller Terézia\\r\\nBalog Rita (osztályfőnök helyettes)\\r\\nRudolfné Liszkai Adrienn\\r\\nÓdor Gabriella\\r\\nSzakmári Csilla\\r\\nWiedemann Krisztina\\r\\nJermendi Andrea\\r\\nBalázs Gábor\\r\\nSzarka Edina\\r\\nNagy Henriett (osztályfőnök)\\r\\nKerekesné Porvay Janka\\r\\nKomjáti Zsuzsanna\\r\\nBóka Győző Örs\\r\\nMarton Sándor\\r\\nBodó Antalné Viola\\r\\nGyarmati László\\r\\nPataki Marianna\\r\\nSóstói Gáborné \\r\\nFucskó Anna\\r\\nHalasy Márton és Mária\\r\\n\\r\\nA többiek a tanítottak még: (szöveggel)\\r\\nCsóka Beáta\\r\\nKósa Edit\\r\\nVarga Csilla\\r\\nDravetzky Katalin\\r\\nLohn Richárd\\r\\nBujdosné Hricisák Viktória\\r\\nAranyos Ildikó"}	\N	2	12 D	2026
12	12	12	Bernáth Kálmán Református Gimnázium - 12 G 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "4f7e356d-cabe-45a3-9a3c-730d3918870d", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 G", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "180", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	145	12 G	2026
15	15	15	Boronkay György Műszaki Technikum és Gimnázium, Vác - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "d82e1691-5f2a-4e34-adda-94a20e5dfc06", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "73", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	41	12 A	2026
24	24	24	Budai Gimnázium és Szakgimnázium - 12 A 2026	1	done	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "865622fa-b23a-47be-aac8-40675069c479", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "181", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	146	12 A	2026
27	27	27	Budai Gimnázium és Szakgimnázium - 12 F 2026	1	waiting_for_response	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "d95bc5e9-4d76-46a2-b6bf-42ad42bad7cc", "color": "#000000", "quote": "\\\\\\"Amikor az élet rád rak még egy lapáttal, tudod mit tegyél? Csak ússz, és evezz! Csak ússz és evezz, ússz és evezz!\\\\\\"", "size_id": "5", "category": null, "sort_type": "megjegyzésben jelöljük", "background": "order/attachments/budai-gimnazium-es-szakgimnazium-12f-12-f-background.pdf", "class_name": "12 F", "class_year": "2026", "order_form": "order/form/budai-gimnazium-es-szakgimnazi-12f-12-f-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>A betűszín fekete legyen. Az alap egy régi, vintage emlékkönyv kétoldalra kinyitva (mintadesign csatolva a háttérkép feltöltése opcióhoz, de az NEM a végleges design!). A lap színe textúrált bézs, mint egy régi könyv. Letisztult de otthonos. A mostani végzős képeink polaroidként, a széléhez “ragasztva” kisebben a kiskori képek. Esetleg gemkapocs, ragasztószalag, bármi ami a vintage stílushoz illik. Néhol a képek szélei meggyűrve, vagy hasonló. A tanárok a könyvön kívül, a szélen, ahogy a képen van. Az alább felsorolt csoportok a leglogikusabb, legkézenfekvőbb módon egymáshoz közel kapjanak helyet a tablón:</p><p>1.csoport: Vivien, Laura, Luca, K.Viki, Csenge, Zoé, Ábel, Zsombor</p><p>2.csoport: Nóri, Paluska Petra, P. Viki, Sári, Norina, Bendegúz, Gaius</p><p>3.csoport: Milán, Dávid, William, Raul, Hamar Bálint - Kiara, Dorka, Orsi. Ada, Jázmin (ha úgy jön ki, akkor (bár ők egy csoportba tartoznak) a fiúkat és a lányokat külön lehet venni.)</p><p>4. Emma, Maja</p><p>Kiskori képek drive-link:</p><p><a href=\\\\\\"https://drive.google.com/drive/folders/1D1Bs0eCknKmORCNNW4lrxaX7l6N40knL?usp=sharing\\\\\\">https://drive.google.com/drive/folders/1D1Bs0eCknKmORCNNW4lrxaX7l6N40knL?usp=sharing</a></p>", "font_family": "Edwardian Script", "old_school_id": "189", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "BERE ÁBEL István\\r\\nBOGNÁR MILÁN\\r\\nDUDÁS KIARA\\r\\nGYENESE RAUL\\r\\nHAMAR BÁLINT Balázs\\r\\nJARÁCS ZOÉ\\r\\nKÓNYA ADA\\r\\nKOVÁCS VIKTÓRIA Petra\\r\\nLEÉB SÁRA Dóra\\r\\nMILU- KOVÁCS NORINA\\r\\nMOGYORÓ LUCA Sára\\r\\nNÁNÁSI ZSOMBOR Márton\\r\\nONDIMA WILLIAM\\r\\nPALUSKA PETRA\\r\\nPAPP VIVIEN Nikoletta\\r\\nPILLER VIKTÓRIA\\r\\nSEMJÉN GÁJUSZ Adrián\\r\\nSIPOS NÓRA Júlia\\r\\nSIVÁK LAURA Petra\\r\\nSTEINDL EMMA Zorka\\r\\nSUGÁR MAJA Virág\\r\\nSZABÓ JÁZMIN\\r\\nSZAKÁL DORKA Emma\\r\\nTAMÁS DÁVID \\r\\nTANKÓ ORSOLYA\\r\\nTÚRI CSENGE\\r\\nVARGA BENDEGÚZ Bence", "teacher_description": "SZABÓ ORSOLYA igazgatónő\\r\\nGAÁL MARIANNA ig.h.\\r\\nBARTHA BEATRIX ig.h.\\r\\nÁBRAHÁM- BURA ANNAMÁRIA osztályfőnök, testnevelés\\r\\nMUNDRUSZ MÁTÉ ofő.helyettes, testnevelés\\r\\nEGYED LIVIA\\tmatematika\\r\\nJUHOS ENIKŐ KLÁRA\\tmatematika\\r\\nGYIVICSÁNNÉ BAKK MARIANNA német\\r\\nMERKELY ISTVÁN KERESZTÉLY testnevelés\\r\\nMÉSZÁROS VANDA testnevelés\\r\\nMIHÁLY HELGA EDIT történelem\\r\\nVRANA- HEITS ANITA\\tmagyar nyelv és irodalom\\r\\nVENCZ ZOLTÁN spanyol nyelv\\r\\nWILHELM MÓNIKA mozgókép és média ismeretek\\r\\nNYULI BOGLÁRKA angol nyelv\\r\\nSÁGI ANDREA olasz nyelv\\r\\nKOZMA GÉZA történelem\\r\\nNAGY ERIKA\\tkémia, fizika"}	2025-11-27 16:20:50	154	12 F	2026
31	31	31	Budai Technikum - 12 DK 2026	1	waiting_for_response	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "370ae950-0eb2-4f9d-a9a3-58eaa73d3ff9", "color": "#000000", "quote": "Bár kívülről mind báránynak látszunk, belül mindegyikünk más-más történetet hordoz. Csak aki igazán ismer minket, látja, kik vagyunk valójában.", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": "order/attachments/budai-technikum-2026-12-dk-background.jpeg", "class_name": "12 DK", "class_year": "2026", "order_form": "order/form/budai-technikum-2026-12-dk-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>Sziasztok!</p><p>Ugyanígy szeretnénk az elrendezést vagy hasonló formában illetve szeretnénk, hogy az idézet jól látható lenne.&nbsp;<br>&nbsp;</p><p>Köszönettel:</p><p>13.dk osztály</p><p>Takács Fruzsina</p>", "font_family": "Grafikusra bízom", "old_school_id": "9", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "1. Balogh Laura\\r\\n2. Balogh Máté\\r\\n3. Barna Olivér\\r\\n4. Berecz Nándor\\r\\n5. Bordás Bence\\r\\n6. Bordás Gergő \\r\\n7. Boross Levente\\r\\n8. Czéh Eugénia\\r\\n9. Csuti Péter\\r\\n10. Dinnyés Karina\\r\\n11. Domaniczky Olivér\\r\\n12. Finta Balázs\\r\\n13. Hovancsik Kitti\\r\\n14. Kárpáti Miklós\\r\\n15. Kassai Csenge\\r\\n16. Kiss Dániel\\r\\n17. Kiszely Petra\\r\\n18. Kovács Katalin\\r\\n19. Modli Zsófi\\r\\n20. Szabó Szelina\\r\\n21. Takács Fruzsina\\r\\n22. Thuranszky Panna\\r\\n23. Toman Hanna\\r\\n24. Trinfa Johanna\\r\\n25. Vanyek Bianka", "teacher_description": "1.\\tMarton-Sugár Klára osztályfőnök \\r\\n2.\\tSeller Attila igazgatóhelyettes \\r\\n3.\\tVeresné-Dongó Katalin  igazgatóhelyettes \\r\\n4.\\tBarta Andrea igazgató\\r\\n5.\\tKrischner Zita \\r\\n6.\\tGarancsine Ágnes \\r\\n7.\\tTatai Beáta\\r\\n8.\\tVégh Andrea\\r\\n9.\\tPuha Zoltán\\r\\n10.\\tBozsaky Csaba \\r\\n11.\\tKatona Beáta\\r\\n12.\\tPisák Ildikó\\r\\n13.\\tLackóné Kiss Ágnes\\r\\n14.\\tDombi Csilla\\r\\n15.\\tRenáta Hentes-Vigh\\r\\n16.\\tPribék Mihály osztályfőnök helyettes \\r\\n17.\\tGazdag Izabella\\r\\n18.\\tMednyászky Tünde\\r\\n19. Nagy Éva\\r\\n20. Pozsgai Petra igazgatóhelyettes"}	2025-09-25 20:07:24	8	12 DK	2026
19	19	19	Boronkay György Műszaki Technikum és Gimnázium, Vác - 12 N 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "d4dfe71d-31c5-48fb-acd9-5c7f7082eb3d", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 N", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "73", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	41	12 N	2026
20	20	20	Bródy Imre Gimnázium és Általános Iskola - 12 B 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "ffa02ad9-119c-424a-8e3a-f361b1b697bd", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 B", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "7", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	6	12 B	2026
21	21	21	Bródy Imre Gimnázium és Általános Iskola - 12 M 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "a2cb429e-ffae-4d3f-8bda-01fb1e9274dc", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 M", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "7", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	6	12 M	2026
23	23	23	Bródy Imre Gimnázium és Általános Iskola - 12 UTE 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "31855f50-736b-4a75-9ba1-671018791fb0", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 UTE", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "7", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	6	12 UTE	2026
25	25	25	Budai Gimnázium és Szakgimnázium - 12 B 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "2201e13c-8402-4efb-bf33-0878630d16b5", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 B", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "181", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	146	12 B	2026
26	26	26	Budai Gimnázium és Szakgimnázium - 12 D 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "b05eafe1-3ad4-48a3-bd0d-a2017bd2cab4", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 D", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "181", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	146	12 D	2026
28	28	28	Budai Technikum - 12 AK 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "c99fcf8d-cb29-4330-8ae3-ebf159791dcb", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 AK", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "9", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	8	12 AK	2026
29	29	29	Budai Technikum - 12 BK 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "adc9de68-dfba-47ae-9528-b588bd626a28", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 BK", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "9", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	8	12 BK	2026
30	30	30	Budai Technikum - 12 CK 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "1153a6b1-9690-4e67-a2a1-4d35e6bf8c9f", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 CK", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "9", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	8	12 CK	2026
37	37	37	I. Géza Király Közgazdasági Technikum - 13 A 2021-2026	1	waiting_for_response	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "3775a7fb-665b-48a9-bb11-cd8612ced996", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "mindenki befele nézzen", "background": "order/attachments/i-geza-kiraly-kozgazdasagi-technikum-2021-2026-13-a-background.jpg", "class_name": "13 A", "class_year": "2021-2026", "order_form": "order/form/i-geza-kiraly-kozgazdasagi-te-2021-2026-13-a-megrendelolap.pdf", "other_file": "order/attachments/i-geza-kiraly-kozgazdasagi-technikum-2021-2026-13-a-otherfile.jpeg", "ai_category": null, "description": "<p>Egyéb csatolmányokba csatoltam a körülbelüli elképzelést. Plusz szeretnénk olyat még rá akik csak tanítottakként legyenek felsorolva tanárok, nem képpel.</p>", "font_family": "Grafikusra bízom", "old_school_id": "95", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Antal Máté \\r\\nPintér Dominik\\r\\nMészáros Nelli Dorottya\\r\\nSógrik Dorina Kata\\r\\nJakab Emma\\r\\nMolnár Lili\\r\\nGerecs Panna\\r\\nArany-Kautz Flóra\\r\\nMajer Luca Emese\\r\\nBurkus Lilla Csilla\\r\\nKatona Áron Róbert\\r\\nMészáros Balázs József\\r\\nHerédi Fanni\\r\\nDános Szilárd\\r\\nKrizsán Balázs\\r\\nKurdi Petra\\r\\nLengyel Benedek\\r\\nVarga Tamara\\r\\nHugyecz Attila\\r\\nHeincz Benedek\\r\\nMakrai Bálint\\r\\nKiss Csenge\\r\\nLénárt Dorina\\r\\nKalhamer Dávid\\r\\nVolentics Emília\\r\\nMátrai Tamás\\r\\nHorváth Máté József\\r\\nSchwarcz Norman\\r\\nPikács Petra\\r\\nVarga Szabolcs\\r\\nVogel Viktória\\r\\nFábián Mária Zita\\r\\nDrajkó Bálint", "teacher_description": "Kép formátumban:\\r\\nHegedűs Gabriella\\r\\nFidesz Ivett\\r\\nPálmainé Rajki Annamária\\r\\nUjhelyiné Rátóti Szilvia Sarolta\\r\\nStareczné Kelemen Éva (osztályfőnök)\\r\\nGesztesi Ildikó\\r\\nKosztra Gábor\\r\\nGreff Tamás\\r\\nJobbágyné Szűcs Tímea\\r\\nOttó Katalin\\r\\nOláh Ferenc(kérdéses még hogy kép formátumban e)\\r\\nFördősné Rozmán Edina( volt igazgató)\\r\\nKovács Noémi( volt igazgatóhelyettes)\\r\\nLeányfalviné Fekete Rózsa\\r\\nPajorné Mennyhárt Mónika(igazgatóhelyettes)\\r\\nGerendai Márk( igazgatóhelyettes)\\r\\nJardek Dániel (volt igazgatóhelyettes)\\r\\nGergely Zsolt ( igazgató)\\r\\nCsapó Imre\\r\\n\\r\\nTanítottak még kategória:\\r\\nCsernák Petra\\r\\nSzilfai-Gyóni Ibolya\\r\\nLangné Oláh Gabriella\\r\\nKreszta Ferenc\\r\\nHegyesi Katalin\\r\\nKővári Dorottya\\r\\nPolka István\\r\\nBérczes Zsuzsanna\\r\\nChovanné Hajdú Éva\\r\\nMcBrayer Billie Lee\\r\\nGergelyné Szűcs Ágnes\\r\\nKettler Andrásné"}	2025-09-25 04:36:14	63	13 A	2021-2026
39	39	39	Illyés Gyula Gimnázium - 12. B 2021-2026	1	waiting_for_response	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "fe5a362d-fdd7-41d2-a62b-f076f68f3451", "color": "#000000", "quote": "Gyere és táncolj!", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": null, "class_name": "12. B", "class_year": "2021-2026", "order_form": "order/form/illyes-gyula-gimnazium-2021-2026-12-b-megrendelolap.pdf", "other_file": "order/attachments/illyes-gyula-gimnazium-2021-2026-12-b-otherfile.jpg", "ai_category": null, "description": "<p>Nagyjából középre kerül egy telefon, rajta megnyitva a BeReal alkalmazás: nagyban egy osztálykép, benne kicsiben a tanárnőkről egy szelfi. ( ezeket a képeket majd később küldjük).</p><p>Köré föntre a tanárok, alulra a diákok tablóképei</p>", "font_family": "Grafikusra bízom", "old_school_id": "118", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Borsós Ábel\\r\\nFaragó Ágoston\\r\\nIlly Ákos\\r\\nFogarasi Áron\\r\\nVinkovits Artúr\\r\\nBanitz Barbara\\r\\nMayer Barna\\r\\nBedő Bendegúz\\r\\nPusztai Boglárka\\r\\nCsanády Boldizsár\\r\\nHorváth Botond\\r\\nKiss Botond\\r\\nHermán Csongor\\r\\nRolly Dalma\\r\\nBuzás Emma\\r\\nKöteles Flóra\\r\\nSárközi Flóra\\r\\nFiedler Gusztáv\\r\\nCsébfalvi Hanna\\r\\nSzeder Janka\\r\\nÁrpádi Júlia Karen\\r\\nKápolnai Kamilla\\r\\nKovács Áron Dániel\\r\\nPuskás Luca\\r\\nBuzás Marcell\\r\\nOláh Márton\\r\\nHreuss Máté\\r\\nSzekszárdi Máté\\r\\nSulina Orsolya\\r\\nReményi Franciska\\r\\nHősei Simon\\r\\nHorváth Szelina\\r\\nCsikós Tünde\\r\\nVarsányi Virág\\r\\nTóth Violetta\\r\\nDakó Zalán\\r\\nNagy-Jávori Zalán", "teacher_description": "Somogyi Zsófia - osztályfőnök - német\\r\\nBene Tünde - igazgató, \\r\\nDoleviczényi Mónika - olasz,\\r\\nTorma Rita - spanyol, \\r\\nSzakálné Gulyás Katalin - matematika, Burián Hana Virginia - matematika, \\r\\nFülöp János - történelem, \\r\\nFüleki Zsombor - angol - média, \\r\\nKapusy Péter -testnevelés,\\r\\nSzép Adrienn - történelem - állampolgári ismeretek, \\r\\nKeresztes Miklós - fizika, \\r\\nGruberné Szilágyi Ágota -biológia, \\r\\nKároly Ildikó - matematika, \\r\\nInges Zsófia - magyar nyelv és irodalom, Vadász András - angol, \\r\\nPéter András Levente - gazdasági ismeretek,\\r\\nKeresztes Emília - angol, \\r\\nTóth Pozsonyi Enikő - biológia, \\r\\nLukácsiné Lehota Edit - földrajz\\r\\nJohnny"}	2025-10-10 07:57:35	85	12. B	2021-2026
36	36	36	Budapesti Fazekas Mihály Gyakorló Általános Iskola és Gimnázium - 12.B 2026	1	not_started	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "2c239ee8-dff4-464e-80f6-6be1bb329546", "color": "#000000", "quote": "\\\\\\"If A is success in life, I should say the formula is A=X+Y+Z, X being work and Y being play Z is keeping your mouth shut.”\\r\\n- Albert Einstein", "size_id": "5", "category": null, "sort_type": "mindenki befele nézzen", "background": "order/attachments/budapesti-fazekas-mihaly-gyakorlo-altalanos-iskola-es-gimnazium-2026-12b-background.jpg", "class_name": "12.B", "class_year": "2026", "order_form": "order/form/budapesti-fazekas-mihaly-gyako-2026-12b-megrendelolap.pdf", "other_file": "order/attachments/budapesti-fazekas-mihaly-gyakorlo-altalanos-iskola-es-gimnazium-2026-12b-otherfile.zip", "ai_category": null, "description": "<p>Készítettünk egy ötlet jellegű tervet, hogy nagyjóból milyen tablót szeretnénk, ezt töltöttem fel háttérkép gyanánt. A tabló Clash Royale témájú lenne. A mi tervünk nem valami szép, például a színek tekintetében. Jó lenne, ha a háttér szebb kék lenne.<br>A terven, felül a tanárok, alul pedig a diákok foglalnának helyet, a két kiemelt helyen pedig az osztályfőnökünk, illetve az igazgató lenne majd.<br>Szeretnénk a segítségedet kérni, hogy milyen háttér lenne jó a tablónkhoz. Kerestem pár ötletet, amik azt gondolom, hogy jók lehetnek. Lehet, hogy a tervhez hasonlóan egy szép kék háttér is jó lehet?</p><p>A tantárgyak megnevezését(pl német vagy német nyelv) pontosítom, mindenesetre odaírtam a tanárok névsorába az általuk tanított tárgyakat.</p><p>Ha később még bármi felmerülne, akkor igyekszem időben jelezni emailben.</p>", "font_family": "Grafikusra bízom", "old_school_id": "123", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Albert Ádám\\r\\nBeke Márton Csaba\\r\\nBertók-Bálint Ticián\\r\\nCzerovszki Milán\\r\\nCsernyik Petra\\r\\nCsorba Dániel\\r\\nErdélyi Dominik\\r\\nHorváth Anna\\r\\nHorváth Zalán\\r\\nHuszty Mária Csenge\\r\\nKocsis Marcell\\r\\nKoncz Krisztián Bertalan\\r\\nKőhegyi Dávid\\r\\nLaduver Péter\\r\\nLiang Jin Yun\\r\\nLuo Han\\r\\nMorvai Gergő\\r\\nNguyen Gia Kiet\\r\\nOlláry Samu\\r\\nPáricsi-Nagy Rezeda\\r\\nPóti Levente\\r\\nRegula Gergely Péter\\r\\nSipos Bodza Lilla\\r\\nStelcz Anna Réka\\r\\nSümeghi Nándor\\r\\nSzita Péter Levente\\r\\nTéti Miklós\\r\\nTiliczki Judit\\r\\nUjvári Sarolta\\r\\nVáradi János\\r\\nVig Viktor Benjámin\\r\\nVu Minh Hoa\\r\\nWan Zhijie Viktor\\r\\nZhang Ziteng\\r\\nZólomy Csanád Zsolt", "teacher_description": "Schramek Anikó, osztályfőnök, fizika\\r\\nSzámadóné Biró Alice Anikó, angol\\r\\nKálmán Levente Zsolt, angol, francia\\r\\nKivovics Judit, angol\\r\\nSzövényi-Luxné Szabó Teréz Ágnes, angol\\r\\nSzabó Katalin, angol\\r\\nLaczkó Ágnes, angol\\r\\nSzabó Márta, német\\r\\nPásztiné Markella Eszter, német\\r\\nTar-Pálfi Nikoletta, német\\r\\nBakuczné Szabó Gabriella, német\\r\\nMüllner Hedda, olasz\\r\\nBecsák Viktória, orosz\\r\\nKeglevich Kristóf, kémia, történelem\\r\\nDr. Nagy Piroska Mária, fizika\\r\\nOrosz Gyula, matematika\\r\\nÁdám Réka, matematika\\r\\nSzeibert Janka, matematika\\r\\nFoki Tamás, történelem\\r\\nSásdi Mariann, informatika/digitális kultúra\\r\\nNémeth Sándor, ének-zene\\r\\nNagy Péter, biológia\\r\\nSólymosné Hirsch Erika, biológia\\r\\nUjlaki Tibor, irodalom\\r\\nGaramvölgyi Béla, rajz/vizuális kultúra\\r\\nTóth Imre, testnevelés\\r\\nKádárné Szalay Eszter, földrajz\\r\\nTakács Márta Éva, filozófia"}	2025-11-26 14:03:59	90	12.B	2026
33	33	33	Corvin Mátyás Gimnázium - 12 B 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "33ac82e5-41ad-4ada-9cce-2519e6611451", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 B", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "10", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	9	12 B	2026
34	34	34	Corvin Mátyás Gimnázium - 12 C 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "99f8cefe-6c4e-47ec-b562-16d33901003c", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 C", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "10", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	9	12 C	2026
35	35	35	Corvin Mátyás Gimnázium - 12 D 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "796916e4-6bca-4640-a9d1-f05897178770", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 D", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "10", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	9	12 D	2026
38	38	38	I. Géza Király Közgazdasági Technikum - 13 B 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "8ed9c6d6-9bcb-43aa-9e12-78be8784e994", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "13 B", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "95", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	63	13 B	2026
41	41	41	Kölcsey Ferenc Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "c900ce76-c95b-4b38-98b5-0244fb817e48", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "96", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	64	12 A	2026
42	42	42	Kölcsey Ferenc Gimnázium - 12 B 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "41ffc655-4533-4536-afc1-3d6738f5f277", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 B", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "96", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	64	12 B	2026
43	43	43	Kölcsey Ferenc Gimnázium - 12 D 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "9434a680-37df-4498-bc80-0e1a6a3a5815", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 D", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "96", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	2025-11-06 19:20:04	64	12 D	2026
44	44	44	Kölcsey Ferenc Gimnázium - 12 E 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "75401154-64fd-4f22-b4e3-30a4c624c41b", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 E", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "96", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	64	12 E	2026
45	45	45	Kölcsey Ferenc Gimnázium - 12 F 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "0be59756-6e2d-4c8e-abe1-9491c7902cfd", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 F", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "96", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	64	12 F	2026
50	50	50	Kőrösi Csoma Sándor Általános Iskola és Gimnázium - 12 C 2026	1	not_started	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "58440383-4961-46de-acba-734e80f3e971", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "megjegyzésben jelöljük", "background": "order/attachments/korosi-csoma-sandor-altalanos-iskola-es-gimnazium-2026-12-c-background.jpg", "class_name": "12 C", "class_year": "2026", "order_form": "order/form/korosi-csoma-sandor-altalanos-2026-12-c-megrendelolap.pdf", "other_file": "order/attachments/korosi-csoma-sandor-altalanos-iskola-es-gimnazium-2026-12-c-otherfile.zip", "ai_category": null, "description": "<p>A tablónk témájának a spotifyt választottuk. Volt egy-két konkrét elképzelésünk a designnal kapcsolatban. Ezeket összefoglalom az alábbi üzenetben.</p><p>Betűtípus: a diákok neve, illetve a zenéből választott idézetek a csatolt képeken arial betűtípussal vannak írva, mivel ez hasonlított leginkább a spotify ikonikus felirataihoz. A tanárok nevét ugyanígy szeretnénk, a konkrét neveket félkövérrel, a tanított tantárgyakat pedig sima, nem félkövérrel.<br>Betűszín: a diákoknak a színes keret miatt fekete, viszont a többi feliratnak és a tanárok neveinek színe még nincs konkrétan kiválasztva, csak annyit tudunk, hogy valamilyen világos színnek kell lennie a sötét háttér miatt.<br>Háttérszín: ez mindenképp sötét kell hogy legyen, a diákok képkereteinek színessége miatt, a képeket csatolom (az egyéb csatolmányokhoz), ezek designjáról volt konkrét elképzelésünk, ezért megszerkesztettük őket, ezen képek mögötti háttér egyelőre sötétkék színűre van állítva, de ezen lehet hogy szeretnénk változtatni attól függően, hogy milyen lesz az összkép, (ha ez problémát jelentene az elküldött képek miatt, bármikor el tudom küldeni újraszínezett háttérrel őket). Volt olyan ötlet ha túl egyszínű és unalmas lenne a háttér egy bizonyos mintát szeretnénk a háttérbe, ami a spotify stílusára jellemző, nem tudom egyébként hogy ez megoldható e mivel erről csak egy inspirációs képünk van, ezt csatolom (a háttérképként feltölthető filehoz). De ez egyébként majd az összképtől függ hogy szeretnénk e, szóval ez a tervezés végső eleme lesz.</p><p>További elképzelésünk, hogy míg a diákok képei színes keretekben vannak, a tanárok képeihez nem szeretnénk kereteket, csak a fotóikat, viszont ezek széleit a keretekhez hasonlóan lekerekítenénk. A diákok egyéni zenéből választott idézetein kívül nem szeretnénk más idézetet, a képeken és egyedi feliratokon kívül csak az iskolánk nevét (Kőrösi Csoma Sándor Általános Iskola és Gimnázium) és osztályunk (12.C) iskolában eltöltött idejét (2021-2026) szeretnénk feltüntetni. Ezen feliratok részletes stílusára még nem volt konkrét elképzelésünk, de nyitottak vagyunk a tervező ötleteire is. Azon tényezőket amikhez nem írtam konkrét elképzelést (pl. tanárok betűszíne) szintén a tervező fantáziájára bízzuk.</p><p>Próbáltam mindent tisztán és érthetően megfogalmazni, de ha valami kérdés vagy probléma merülne fel ezen az email címen elérhető vagyok:&nbsp;<a href=\\\\\\"mailto:hanna.vilmanyi@gmail.com\\\\\\">hanna.vilmanyi@gmail.com</a>. Szeretnénk kapcsolatot tartani a tervezővel, némileg belelátni a tervezés folyamatába, konzultálni, ötletelni a tablónkról. Ha lehetséges először szeretnénk néhány ötletet látni az elrendezéssel kapcsolatban, ugyanis ezt megszavaznánk osztályszinten. Az elrendezést nem névsorrendben szeretnénk, hanem a színes keretek szerint, (nem színsorrendben) a lényeg hogy azonos vagy hasonló színek ne kerüljenek egymás mellé, és esztétikailag kielégítő legyen.&nbsp;</p>", "font_family": "Grafikusra bízom", "old_school_id": "98", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Osztálynévsor:\\r\\nCsóka Hanna Boglárka\\r\\nCsoma Linda Szófia\\r\\nDomján Alexa\\r\\nGili Nóra Márta\\r\\nGilicze Dorka\\r\\nGyarmati Emma\\r\\nGyarmati Lilla\\r\\nHalla Bianka\\r\\nHartai Vencel György\\r\\nHellebrandt Noémi\\r\\nHorgász Boldizsár\\r\\nKardos Kinga\\r\\nKozelkin Dária\\r\\nKőfaragó Mátyás\\r\\nKövér Zsófia\\r\\nKrálik Márton Pál\\r\\nLőrinczi Linda\\r\\nMészáros Márk Máté\\r\\nMezei Balázs Dávid\\r\\nNagy-Bíró Blanka\\r\\nNagy-Bíró Eliza\\r\\nOláh Bianka\\r\\nOláh Regina\\r\\nPatócs Ákos\\r\\nPetrák Ádám\\r\\nTaar Ádám László\\r\\nTassy Roland\\r\\nTóth Vivien\\r\\nVadász Dávid\\r\\nVályi-Nagy Mira\\r\\nVarga Alíz\\r\\nVilmányi Andrea Hanna", "teacher_description": "Tanárok:\\r\\nosztályfőnök, testnevelés: Kovács Kálmán\\r\\nigazgató: Kuli Ferenc, Dr. Hicz János\\r\\nosztályfőnök helyettes, igazgatóhelyettes, magyar nyelv és irodalom: Günther Győző\\r\\nmagyar nyelv és irodalom: Juhászné Makovics Erzsébet,\\r\\nmatematika: Papp Rebeka, Kassainé Málnási Ágnes\\r\\ntörténelem: Gönczi Sándor\\r\\nangol nyelv: Lakos Erika, Tamás Tünde\\r\\nfrancia nyelv: Jakus-Halász Katalin\\r\\nnémet nyelv: Simon László\\r\\nfizika: Ujvárosi Emese\\r\\nföldrajz: Braxátor Marianna\\r\\nkémia: Fülöpné Kakas Zsuzsa\\r\\nbiológia: Marosvölgyi Veronika\\r\\ndigitális kultúra: Horváth Gergely Bálint, Gegő Kinga\\r\\nvizuális kultúra: Domokos Berta\\r\\nének zene: Csenki Katalin\\r\\nállampolgári ismeretek: Bíró Gergely Levente\\r\\nmozgóképkultúra és médiaismeret: Kolláth Károly Ferenc\\r\\n\\r\\nmegemlítés (kép nélkül): Bota Melinda, Fenyves Bettina, Ivanics Beáta, Papp Erika, Simon Mira, Kelemen Dénes Lehel"}	2025-11-21 15:56:28	66	12 C	2026
47	47	47	Könyves Kálmán Gimnázium - 12 D 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "9668789e-dec7-4c51-8e4e-7bd799de06e7", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 D", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "136", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	102	12 D	2026
48	48	48	Kőrösi Csoma Sándor Általános Iskola és Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "24d06ed9-1804-4dbc-ac15-aad12c1fb6d7", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "98", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	66	12 A	2026
49	49	49	Kőrösi Csoma Sándor Általános Iskola és Gimnázium - 12 B 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "3427e7d6-2e46-4228-a61a-e5edb5c6cbed", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 B", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "98", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	66	12 B	2026
51	51	51	Kreatív Technikum - 13 G 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "aca65234-2edd-44f4-b528-20061b842336", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "13 G", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "97", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	65	13 G	2026
53	53	53	Váci Madách Imre Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "c15b5af2-60dc-4bbb-8e41-b6398fd599ba", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "64", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	32	12 A	2026
59	59	59	Móricz Zsigmond Gimnázium - 12 B 2026	1	not_started	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "3a0ac464-a35d-4e12-b2b2-d74f66168dcd", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "mindegy", "background": "order/attachments/moricz-zsigmond-gimnazium-2026-12-b-background.png", "class_name": "12 B", "class_year": "2026", "order_form": "order/form/moricz-zsigmond-gimnazium-2026-12-b-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>A témánk a szerencse játék, kaszinó,</p><p>black jack, kaparós sorsjegy</p><p>Arra gondoltunk h minden tanulo kerete lehetne egy francia kartya tehat peldálul a kep sarkaban ott van, hogy „pikk 7\\\\\\" stb</p>", "font_family": "Grafikusra bízom", "old_school_id": "14", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Bíró Borbála\\r\\nBodolai Jázmin Anna\\r\\nBorbély Anna Borbála\\r\\nCsépai Benedek\\r\\nCsepely Bálint\\r\\nCsillag Miklós\\r\\nCsobádi Nelli\\r\\nFelső Gréta\\r\\nFonyó Adám\\r\\nGalló Noémi Léna\\r\\nGuller András\\r\\nGyurkovics Bálint\\r\\nHorváth Kristóf Nándor\\r\\nJeles Dániel Péter\\r\\nKérdő Áron András\\r\\nLombard-Eszes Marion Amélie\\r\\nMészáros Flóra Margit\\r\\nMészáros Hunor Gábor\\r\\nNagy-Horváth Csaba\\r\\nQuintavalle Fabio\\r\\nTörök Titusz Aurél\\r\\nToth Fédra Alexandra\\r\\nÜveges Kornél Zoltán\\r\\nVáradi Zsombor\\r\\nZana Zoé Martina\\r\\nZsiga Emese Dorka\\r\\nPesti Dalma", "teacher_description": "Bärnkopf Bence\\r\\nTassi Balázs\\r\\nPoór-Bagyinszki Diána\\r\\nSomodi Zoltán\\r\\nGyörgy Dániel\\r\\nGábor Attila\\r\\nInstitórisz László\\r\\nPaulikné Reicher Andrea\\r\\nGubiczáné Gombár Csilla\\r\\nSpolarich Tünde\\r\\nSzabó Klára\\r\\nBata Dániel\\r\\nKnyihár Amarilla\\r\\nValkai Borbála\\r\\nSzűcs Eszter\\r\\nPalkovics Krisztina\\r\\nNagy Anita\\r\\nHambuch Mátyás\\r\\nBereczki Réka\\r\\nDr. Osváth László\\r\\nHerczegh Gabriella\\r\\nMészáros Mátyás\\r\\nSzalai Kornélné"}	2025-10-08 04:15:01	13	12 B	2026
60	60	60	Móricz Zsigmond Gimnázium - 12 C 2026	1	not_started	t	2025-11-28 05:12:26	2025-11-30 19:23:40	{"uuid": "fc1f405d-9cf7-42d9-9823-e1df7f8d9f72", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 C", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "14", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	13	12 C	2026
56	56	56	Váci Madách Imre Gimnázium - 12 D 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "14f6a23d-1227-4481-800d-947de3ab32f6", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 D", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "64", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	32	12 D	2026
58	58	58	Móricz Zsigmond Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "b2c58ac9-e3d9-42f1-995c-26e26dd12077", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "14", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	13	12 A	2026
61	61	61	Móricz Zsigmond Gimnázium - 12 D 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "ae464cf6-a038-42c2-b734-8fd18d34480e", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 D", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "14", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	13	12 D	2026
62	62	62	Móricz Zsigmond Gimnázium - 12 F 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "ceacdddd-2e27-4f75-94ae-e78653da57ef", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 F", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "14", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	13	12 F	2026
72	72	72	Szent István Gimnázium - 12 C 2026	1	waiting_for_response	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "137eb556-3512-4030-b7ce-1dd8390c3c4c", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "megjegyzésben jelöljük", "background": "order/attachments/szent-istvan-gimnazium-2026-12-c-background.jpg", "class_name": "12 C", "class_year": "2026", "order_form": "order/form/szent-istvan-gimnazium-2026-12-c-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>Börtön téma</p><p>Mindenki alá odaírva, hogy miért tartóztatják le.</p><p>Ofő valahol a tabló közepén helyezkedne el, rendőrt alakítva</p><p>&nbsp;</p><p>A csatolt háttérkép nem a teljes tabló háttereként, hanem minden diák képe mögé lett elképzelve. A teljes háttér valami rácsos lenne, csak nem találtunk túl jókat.</p><p>&nbsp;</p><p>A fényképeket az osztály lány:fiú 1:2 arányában szeretnénk elhelyezni valamilyen módon. pl. 1 adag fiú, 1 adag lány, 1 adag fiú…</p><p>&nbsp;</p><p>A tanári névsorban szereplő következő neveket fénykép nélkül, csak szövegesen szeretnénk említeni, hogy tanítottak:</p><p>Kovács-Veres Tamás<br>Jahoda Anna<br>Bartháné Nagy Katalin<br>Forgóné Szabó Zsuzsanna<br>Dörögdi József<br>Bóka Gábor<br>Fürész Blanka<br>Kiss Gabriella<br>Jakab Edit<br>Fruchter Diána</p>", "font_family": "Grafikusra bízom", "old_school_id": "17", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Adorján Kristóf Milán\\r\\nBaráz Kornél\\r\\nBlum Blanka\\r\\nCsáki Ákos\\r\\nDobó-Nagy Mátyás Pál\\r\\nGaray Borbála Zsuzsanna\\r\\nGoddard Marcell\\r\\nHaár Gordon\\r\\nHarkai Emma\\r\\nHegyi Márton\\r\\nHorváth Ábel Nándor\\r\\nKámán Domonkos\\r\\nKincses Milán\\r\\nKirály Samu\\r\\nKiss Gergely\\r\\nKovács Benedek\\r\\nLi Xiang\\r\\nMező Marcell Gyula\\r\\nMihályi Olivér\\r\\nMura István\\r\\nNémeth Barnabás\\r\\nNguyen Ha Anh\\r\\nPrácser András\\r\\nSeres Kata\\r\\nSimon Vince\\r\\nSzánthó Regina Dorka\\r\\nSzél Márton\\r\\nSzlovák Tímea\\r\\nSzomolai Szabolcs\\r\\nSzűcs Réka\\r\\nTong Shuyu\\r\\nTurán Lilla\\r\\nUlrich Gábor\\r\\nVadas János Bendegúz\\r\\nVégh Albert Vince\\r\\nZhang Jiayi", "teacher_description": "Szecsődi Tamás - osztályfőnök, magyar irodalom és magyar nyelv\\r\\nLázár Tibor - igazgató\\r\\nDr Borbás Réka - általános igazgatóhelyettes\\r\\nKovács Péter - nevelési igazgatóhelyettes\\r\\nHalek Tamás - matematika\\r\\nDankowsky Anna Zóra - matematika, ének-zene\\r\\nDr Bajkó Ildikó - fizika\\r\\nMiklós Zoltán - kémia\\r\\nDr Sumi Ildikó - biológia\\r\\nSeregély Ildikó - földrajz\\r\\nBerke Ildikó - angol nyelv\\r\\nBabus Zoltán - angol nyelv\\r\\nHorváthné Gődény Judit - angol nyelv\\r\\nHős Csilla - angol nyelv\\r\\nRuszina Mónika - olasz nyelv\\r\\nVimlátiné Kálmán Judit - francia nyelv\\r\\nCsepregi-Horváth Zsófia - informatika\\r\\nKempl Klára - testnevelés\\r\\nForrás Péter - testnevelés\\r\\nKósa Zsolt - történelem\\r\\nSzabó András - biológia\\r\\nKelemen Gabriella - német nyelv\\r\\nInokai Máté - ének-zene\\r\\nTugyi Tímea - rajz\\r\\nMagyar Zsolt - matematika\\r\\nJuhász István - matematika\\r\\nGaár Orsolya - történelem\\r\\nSzatmáry Zsolt - fizika\\r\\nPorkoláb Panna Klára - német nyelv\\r\\nAbuczki Erika - informatika\\r\\nTamás Beáta - matematika\\r\\nForgó Levente - testnevelés\\r\\nKovács-Veres Tamás - történelem\\r\\nJahoda Anna - német nyelv\\r\\nBartháné Nagy Katalin - német nyelv\\r\\nForgóné Szabó Zsuzsanna - rajz\\r\\nDörögdi József - német nyelv\\r\\nBóka Gábor - latin nyelv, média\\r\\nFürész Blanka - olasz nyelv\\r\\nDr Kiss Gabriella - dráma\\r\\nJakab Edit - magyar irodalom\\r\\nFruchter Diána - angol nyelv"}	2025-10-10 08:06:51	16	12 C	2026
70	70	70	Dunakeszi Radnóti Miklós Gimnázium - 12.B. 2021-2026	1	not_started	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "219e73b2-cb63-4be2-9eba-3cfbda6f49ea", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "mindenki befele nézzen", "background": "order/attachments/dunakeszi-radnoti-miklos-gimnazium-2021-2026-12b-background.jpeg", "class_name": "12.B.", "class_year": "2021-2026", "order_form": "order/form/dunakeszi-radnoti-miklos-gimna-2021-2026-12b-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>A csatolt képet szeretnénk háttérnek.&nbsp;</p><p>A tanárokat felülre szeretnénk elhelyezni, &nbsp;a diákokat pedig a minta vonalát követve U alakban az aljára. Nem baj ha rálóg, természetesen, mert nagyon nagy, csak kövesse.</p><p>Az iskola nevét, az évet, és az osztályt középre szeretnénk.</p><p>Az 2 osztályfőnököt középre és a diákokhoz legközelebbi tanári sorban, a 2 osztályfőnök helyettest feléjük.</p><p>Az igazgató úrat és az igazgatóhelyetteseket az U alak 2 oldalának a 2 részére a diákok felé közvetlen szertnénk.</p><p>Az utolsó diák, Sárközi Mihály kérdéses, hogy rajta lesz-e a tablón évismétlés miatt, ez a fotózáson kiderül.</p><p>Gál Zoltán Tanár Úr tavaly elhunyt, így az Ő képét majd digitális formában elkérjük, szeretnénk , hogy felkerüljön.</p><p>Vannak tanáraink, akiket nem szeretnénk képpel, csak szöveggel, ABC sorrendben a tablóra rakni a következőkép: “Tanítottak:\\\\\\"</p>", "font_family": "Italianno", "old_school_id": "107", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Békési Blanka Dóra\\r\\nBerki Dóra\\r\\nBihari Zsófia\\r\\nBöőr Bíborka\\r\\nButenkov Emma\\r\\nDienes Panna\\r\\nErdélyi Zalán\\r\\nFarkas Virág\\r\\nFekete Kinga\\r\\nFelker Hanna\\r\\nFerencz Kristóf\\r\\nFüredi-Trummer András\\r\\nHollósy Dániel Balázs\\r\\nHorváth Hanna Edina\\r\\nIpacs Dóra\\r\\nJuhász Róbert\\r\\nKassa Zoé\\r\\nKelenczés Orsolya\\r\\nLukács Dominik\\r\\nMarkó Marcell\\r\\nMiron Kata\\r\\nNémeth Rebeka\\r\\nOrbán Réka\\r\\nPölczman Dávid\\r\\nReznek Laura\\r\\nSárosi Katica\\r\\nSebestyén Zente\\r\\nSolymosi Luca\\r\\nSuominen Lelle Hilla\\r\\nSzabó Csongor\\r\\nSzabó Vince Csanád\\r\\nSzalai Petra\\r\\nSzanyó András\\r\\nTomes Milán Dávid\\r\\nTóth Gergely\\r\\nVirág Luca\\r\\nSárközi Mihály*", "teacher_description": "Képpel:\\r\\nÁbrahám Hedvig\\r\\nAlmásiné Nemeshegyi Gyopárka\\r\\nBurjánné Török Orsolya\\r\\nCsordás Lászlóné\\r\\nDányi-Szabó Katalin\\r\\nFarkas Éva\\r\\nGál Zoltán*\\r\\nHársfalvi Anikó\\r\\nHernyákné Molnár Tünde\\r\\nIllés Zoltánné Ujvári Éva\\r\\nKrix Antalné\\r\\nKömley Pálma\\r\\nLengyel-Precskó Lilian (Osztályfőnök)\\r\\nLutter András\\r\\nParóczay Eszter\\r\\nPéczeli Ádám (Osztályfőnök helyettes)\\r\\nPodányi Viktória\\r\\nSimánné Horváth Zsuzsanna\\r\\nSzegedi-Viczencz Katalin\\r\\nSzűcsné Stadler Lilla (Osztályfőnök)\\r\\nTakács Erika (Osztályfőnök helyettes)\\r\\nTuzson-Berczeli Tamás\\r\\nNyiri István (Igazgató)\\r\\nSzilágyiné Manasses Melinda (Igazgatóhelyettes)\\r\\nMajer Tamás (Igazgatóhelyettes)\\r\\nTarjánné Sólyom Ildikó (Igazgatóhelyettes)\\r\\n\\r\\nSzöveggel:\\r\\nAranyné Vékes Klára\\r\\nBallagó Kornél\\r\\nChira Csongor \\r\\nÉles Zoltán\\r\\nHarmos Ildikó\\r\\nHorváth Bernadett\\r\\nHorváth Henrietta\\r\\nIan Francis Jedlica\\r\\nKolossa Katalin\\r\\nNagy Péter Gábor\\r\\nRátki Ilona\\r\\nReményi Edina\\r\\nUnyi Tamás"}	2025-11-21 15:44:32	74	12.B.	2021-2026
71	71	71	Radnóti Miklós Gimnázium - 12 D 2026	1	not_started	t	2025-11-28 05:12:26	2025-11-30 19:23:25	{"uuid": "f6c02f5f-e0ea-4f7f-b64e-50324a50dd66", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 D", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "100", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	68	12 D	2026
64	64	64	Nagy Sándor József Gimnázium - 12 B 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "03282602-63b4-4e6f-adb4-3126596ef3be", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 B", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "184", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	149	12 B	2026
65	65	65	Petőfi Sándor Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "d9b2c8d5-ead0-4bac-9570-6c9064067ce5", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "16", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	15	12 A	2026
66	66	66	Petőfi Sándor Gimnázium - 12 B 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "52bbaa86-097f-419a-b2aa-be0795253bcc", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 B", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "16", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	15	12 B	2026
67	67	67	Petőfi Sándor Gimnázium - 12 C 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "13f51296-2a36-48a0-aea6-77bc28ac546f", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 C", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "16", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	15	12 C	2026
68	68	68	Petőfi Sándor Gimnázium - 12 E 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "cc7e1237-ed12-474f-9182-81d02b380acb", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 E", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "16", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	15	12 E	2026
69	69	69	Radnóti Miklós Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "2555e03d-59cb-4e99-9c68-bddf06befce2", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "100", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	68	12 A	2026
73	73	73	Szent István Gimnázium - 12 D 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "923cf709-e008-4984-b801-c0d6dad47840", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 D", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "17", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	16	12 D	2026
88	88	88	Pilisvörösvár Friedrich Schilleg Gimnázium - 12. c ???? - 2026	3	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "4233de7e-3a63-4ab3-9526-22bed2744ee7", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12. c", "class_year": "???? - 2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "126", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	91	12. c	???? - 2026
89	89	89	Budapest-Fasori Evangélikus Gimnázium - 12. A ???? - 2026	3	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "9e555c4f-f5da-4f77-8d4b-a847ecb66f17", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12. A", "class_year": "???? - 2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "139", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	105	12. A	???? - 2026
82	82	82	Zrínyi Miklós Gimnázium - 12 B 2026	1	waiting_for_photos	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "07911d10-56cc-4313-b181-010dd1006e2f", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "mindegy", "background": "order/attachments/zrinyi-miklos-gimnazium-2026-12-b-background.jpg", "class_name": "12 B", "class_year": "2026", "order_form": "order/form/zrinyi-miklos-gimnazium-2026-12-b-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>Ilyen tematikajú tablót szeretnénk készítettni, amit a csatolt fájlban küldtem. Annyi változtatással, hogy a tanárokat is fel szeretnénk rá rakni + egy olyan névsort hogy tanítottak még… akikről nem készül tanárról fénykép.</p><ul><li>a fenti osztályfotó, a szalagavatón fog elkészülni, így azt utólag tudjuk elküldeni.</li><li>A háttér világosabb színűre szerenténk,</li><li>(Pl.szürke). És a hozzá a “ködöt” pedig burgundi színűre szeretnénk, mivel olyan színű lesz a ruhánk.</li></ul><p>-az lenne a kérdésem, hogy a tanári névsoron még lehet e változtatni ? Arra gondolok hogy ha valaki nem szeretne mondjuk a dr. A neve elé vagy a harmadik nevét, akkor azon lehet e még változtatni, ha hétfőn kiderítem.Előre is köszönöm.</p>", "font_family": "Grafikusra bízom", "old_school_id": "22", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Batizi- Pócsi Eszter \\r\\nBerkes Dalma \\r\\nBernáth Lívia \\r\\nBoda Dzsenifer Liliána \\r\\nCsányi- Nagy Fédra \\r\\nCsaplár Bernadett \\r\\nCsertán Laura \\r\\nCsizmadia László \\r\\nFaragó- Szuhay Márton \\r\\nFülöp Zoltán \\r\\nHertelendy Anna \\r\\nKendrovics László \\r\\nKéri Máté \\r\\nLaczkó Dávid\\r\\nLukáts Szófia \\r\\nMadai Csenge \\r\\nMészáros Nóra \\r\\nMüller Liza \\r\\nÓnodi Liliána Tímea \\r\\nPál Réka \\r\\nPalyaga Bálint \\r\\nPan Yi\\r\\nRostás Máté Magor \\r\\nRuszányuk Kristóf\\r\\nSzabó Hanna Dóra \\r\\nSzabó Viola \\r\\nSzűcs Bianka \\r\\nTichy Cintia \\r\\nTőzsér Cintia Alexa\\r\\nVartik Réka\\r\\nVastag Cintia \\r\\nVinyarszki Ádám Dániel", "teacher_description": "- Szabó Anikó \\r\\n- Fehér András Tamás\\r\\n- Simon Ildikó \\r\\n- Edényi László \\r\\n- Péntek Viktória \\r\\n- (Dr.)Kaposi Napsugár \\r\\n- Varga Orsolya\\r\\n- Pappné Balla Katalin\\r\\n- Tóth Gyöngyi \\r\\n- Szabó György \\r\\n- Pataki Antalné \\r\\n- Balázs László \\r\\n- Hajdu Péter \\r\\nPásztor Ildikó \\r\\nBakosi Valéria\\r\\nBakosi Magolna\\r\\n- csak névként szeretnénk feltüntetni; \\r\\n-Rigóczky Csaba \\r\\n- (Dr.) Klicasz Szpirosz\\r\\n- Boronkai Eszter\\r\\n- Lukacsné Papp Csilla\\r\\n-Szabó János \\r\\n-Kassitcky Tamás \\r\\n- Fekete Ferencné\\r\\n-Faragó Tibor"}	2025-09-25 14:09:15	21	12 B	2026
85	85	85	I. Géza Király Közgazdasági Technikum - 13.C 2020 - 2026	1	waiting_for_response	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "e21df5e8-d244-47c0-b670-dac185e805cc", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "megjegyzésben jelöljük", "background": "order/attachments/i-geza-kiraly-kozgazdasagi-technikum-2020-2026-13c-background.jpg", "class_name": "13.C", "class_year": "2020 - 2026", "order_form": "order/form/i-geza-kiraly-kozgazdasagi-te-2020-2026-13c-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>minden diák neve alá szeretnénk egy idézetet, olyasmit, mint ami az évkönyvekben szokott lenni</p><p>a hátteret csatoltuk, de nem ragaszkodunk hozzá kifejezetten, viszont a bézses téma maradjon meg, ilyesmire gondoltunk</p><p>az idézetekről küldünk majd listát (mennyi időnk van rá?) a tablóképek elhelyezését mi szeretnénk megszabni, ha lehetséges</p>", "font_family": "Grafikusra bízom", "old_school_id": "95", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Balázs Viktória\\r\\nBánfi Bence\\r\\nBeno Santiago\\r\\nBódi Kristóf\\r\\nBudai Lili\\r\\nCzinege Balázs\\r\\nDulkai Csenge Larina\\r\\nFarkas Lorina\\r\\nFöldi Patrik\\r\\nGerman Luca\\r\\nGyarmati Levente\\r\\nHalasi Hanna Imola\\r\\nHrozina Blanka\\r\\nJakus Nóra\\r\\nKovács Kinga\\r\\nKurucz Hanga\\r\\nLeskó Laura Anna\\r\\nLévai Anna\\r\\nLomen Anna\\r\\nNádudvari Tamás\\r\\nPalotás Lilien\\r\\nPápai Dóra Jozefin\\r\\nPapp Sára\\r\\nPokorny Dániel Zsombor\\r\\nRagács Róbert Zoltán\\r\\nRammer Martin\\r\\nSarankó Liza\\r\\nSári Anna Virág\\r\\nTemesvári Vivien\\r\\nThury Kitti\\r\\nTörő Panka\\r\\nTüske Balázs Gábor\\r\\nZemeny Gréta", "teacher_description": "Képpel:\\r\\nGergely Zsolt (igazgató)\\r\\nPajorné Menyhárt Mónika (igazgatóhelyettes)\\r\\nFördősné Rozmán Edina (volt igazgató)\\r\\nGerendai Márk (igazgatóhelyettes)\\r\\nVégh-Alpár Noémi (osztályfőnök)\\r\\nGreff Tamás\\r\\nKővári Dorottya\\r\\nOrosz Vivien\\r\\nDr. Szénásy Andrea\\r\\nSelényi Beatrix Vanda\\r\\nHegyesi Katalin\\r\\nVarga Beáta\\r\\nSzilfai-Gyóni Ibolya\\r\\nDrajkó Gergő\\r\\nOtóné István Eszter\\r\\nKovács Noémi\\r\\n\\r\\nKép nélkül:\\r\\nPappné Lőrincz Klaudia\\r\\nHinelné Gácsi Krisztina\\r\\nKreszta Ferenc\\r\\nIstván Éva\\r\\nJobbágyné Szűcs Tímea\\r\\nPálmainé Rajki Annamária\\r\\nÁrmós Zoltán\\r\\nGesztesi Ildikó"}	2025-10-25 14:36:34	63	13.C	2020 - 2026
78	78	78	Vak Bottyán János Katolikus Technikum, Gyöngyös - 13 A 2026	1	not_started	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "3e004f71-ec51-4e28-8333-3b01c0005314", "color": "#000000", "quote": "\\\\\\"A jelen az övék; a jövő, amiért valóban dolgoztam, az enyém.”\\r\\n/Nikola Tesla/", "size_id": "5", "category": null, "sort_type": "mindenki befele nézzen", "background": "order/attachments/vak-bottyan-janos-katolikus-technikum-gyongyos-2026-13-a-background.jpg", "class_name": "13 A", "class_year": "2026", "order_form": "order/form/vak-bottyan-janos-katolikus-te-2026-13-a-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>A tabló alapötlete egy elektronikai szimulációs szoftver (Tina) felülete. A színei fontosak! Az osztály (23 tanuló + osztályfőnök) egy áramkört alkot, a fényképek logikai kapukban vannak elhelyezve. A kapuk oldalarányai a képekhez alakíthatók. A csomópontok a mintának megfelelő helyeken legyenek. A Tanáraink eszköztárban 22 tanár (reméljük van fényképe önöknél mindenkinek), + feliratként a többi tanár aki tanított az osztályban.</p><p>Tanáraink voltak:</p><p>Bátoriné Zaja Éva<br>Dénes Eliza<br>Dér Tibor<br>Faragó László<br>Horváth Zoltán<br>Király Ferencné<br>Pádár Miklós<br>Simon Péter<br>Udvari Judit<br>Zsoldosné Borosi Beáta<br>&nbsp;</p>", "font_family": "Gisha", "old_school_id": "59", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Barta Máté\\r\\nBencze Tamás\\r\\nBencsik András\\r\\nBesze Gergő\\r\\nÉliás Máté\\r\\nHámbor Arnold\\r\\nHordós Barnabás\\r\\nKatona Tamás\\r\\nKirály Dániel\\r\\nKovács Bence\\r\\nKovács Megyer\\r\\nNagy Botond Bendegúz\\r\\nPapp Simon\\r\\nPolnai Alexander Illés\\r\\nRafael Péter\\r\\nSimonyák Dávid\\r\\nStriteczky Attila\\r\\nSzabó Gergő\\r\\nSzankó Dominik Richárd\\r\\nSzilágyi Zsombor\\r\\nZombori Norman\\r\\nZsarnai Balázs\\r\\nZsarnai Zalán", "teacher_description": "Atya még nem tudjuk melyik - plébános\\r\\nBenyovszky Péter - igazgató\\r\\nBorbás József – szakmai igazgatóhelyettes\\r\\nHevér Tibor- műszaki vezető\\r\\nHordós Andrea – tanulmányi igazgatóhelyettes\\r\\nTolmayerné Borbély Zsuzsanna – nevelési igazgatóhelyettes\\r\\nSzalai Zsuzsanna - kollégiumvezető\\r\\nKaló István - osztályfőnök\\r\\nÁdám Sándor\\r\\nBabus Mónika\\r\\nBalog Tivadar\\r\\nBorbélyné Remes Zsuzsa\\r\\nDr.Petrovicsné Sasvári Zsuzsanna\\r\\nFarkas László\\r\\nFodor Judit\\r\\nGál Géza\\r\\nHarkó Erzsébet\\r\\nPatai Gábor\\r\\nSimon Veronika\\r\\nSólyomvári Tamás\\r\\nTomcsik Erika\\r\\nVárallyay Johanna\\r\\nVarga Csaba"}	2025-10-25 13:52:38	27	13 A	2026
77	77	77	Teleki Blanka Gimnázium - 12 C 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "b2407081-d5c3-4fef-beab-20f6afa84f30", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 C", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "102", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	70	12 C	2026
79	79	79	Vak Bottyán János Katolikus Technikum, Gyöngyös - 13 C 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "21bc394f-cb05-4099-8c6f-921bac70f249", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "13 C", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "59", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	27	13 C	2026
80	80	80	Vak Bottyán János Katolikus Technikum, Gyöngyös - 13 D 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "7e1cdf0a-9bd4-445a-885e-40faf7cdd746", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "13 D", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "59", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	27	13 D	2026
81	81	81	Zrínyi Miklós Gimnázium - 12 A 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "630967fe-18b7-4a44-898b-cd4663758042", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 A", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "22", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	21	12 A	2026
83	83	83	Zrínyi Miklós Gimnázium - 12 D 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "669e0c9e-c368-468c-8618-5baa07e2b5bf", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12 D", "class_year": "2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "22", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	21	12 D	2026
84	84	84	Kreatív Technikum - 13.R ???? - 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "5ec14220-0289-492a-bb65-ec8009aaea27", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "13.R", "class_year": "???? - 2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "97", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	65	13.R	???? - 2026
86	86	86	Pilisvörösvár Friedrich Schilleg Gimnázium - 12. A ???? - 2026	3	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "6ed6e993-5150-48c3-9b6e-f2da99b326af", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12. A", "class_year": "???? - 2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "126", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	91	12. A	???? - 2026
87	87	87	Pilisvörösvár Friedrich Schilleg Gimnázium - 12. B ???? - 2026	3	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "48dcec60-25e3-42eb-bce1-a7366bb01539", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12. B", "class_year": "???? - 2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "126", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	91	12. B	???? - 2026
91	91	91	Budapest-Fasori Evangélikus Gimnázium - 12. C 2022 - 2026	3	waiting_for_response	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "73971ac1-6c26-4339-bc0a-fb5bea05d673", "color": "#000000", "quote": "", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": null, "class_name": "12. C", "class_year": "2022 - 2026", "order_form": "order/form/budapest-fasori-evangelikus-gi-2022-2026-12-c-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>A megbeszélt rulettes / kaszinós témában szeretnénk</p>", "font_family": "Grafikusra bízom", "old_school_id": "139", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Bitemo Artúr Márk\\r\\nMácsai István\\r\\nBagyinka Anna\\r\\nSzpisák András Miroslav\\r\\nFarszky Máté\\r\\nLaskovics Milán Máté\\r\\nZahorán Szabolcs János\\r\\nBánhegyi Ákos\\r\\nNász Roland\\r\\nKomoly Flórián Iván \\r\\nValér Miklós Norbert \\r\\nBóday Dávid \\r\\nJancsó Gábor Zsolt\\r\\nBujdosó Antal Károly\\r\\nBorzák Bonifác Péter\\r\\nHajnal-Tóth Ádám\\r\\nBorzován András\\r\\nMalomka Vanessza\\r\\nMohácsi Hanga\\r\\nErdős Gabriella Fruzsina\\r\\nErdősi Réka\\r\\nWalkó Abigél\\r\\nKis Nataly Zsofi\\r\\nDicső Bíborka Krisztina\\r\\nMálits Luca Nóra\\r\\nNarancsik Luca\\r\\nStefkó Anna Flóra\\r\\nMátó Nóra", "teacher_description": "Anikó néni\\r\\nTimi néni\\r\\nBencze Dávid\\r\\nGalgóczy Gábor\\r\\nNagy Sándor\\r\\nVarga Tamásné\\r\\nCsepregi András\\r\\nKárolyfalvi Zsolt\\r\\nHűvös Tamás\\r\\nCsernus Rita\\r\\nSchranz Ambrus\\r\\nSzűcs Emese\\r\\nValló Eszter\\r\\nKovácsné Gyarmathi Krisztina\\r\\nKarakas Mariann\\r\\nEcker Anita\\r\\nCzapekné Egervári Orsolya\\r\\nGianone Kinga\\r\\nFabiny Márton\\r\\nTasnádi Zsuzsanna \\r\\nHámor Endre"}	2025-10-25 13:53:42	105	12. C	2022 - 2026
93	93	93	Pasaréti Gimnázium - 12. B 2022 - 2026	4	in_print	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "070abd59-2d06-4558-89ef-95d645834820", "color": "#000000", "quote": "nincs idézet", "size_id": "5", "category": null, "sort_type": "abc sorrend", "background": "order/attachments/pasareti-gimnazium-2022-2026-12-b-background.jpg", "class_name": "12. B", "class_year": "2022 - 2026", "order_form": "order/form/pasareti-gimnazium-2022-2026-12-b-megrendelolap.pdf", "other_file": null, "ai_category": null, "description": "<p>A tablóháttér Botticelli Vénusz születése című festménye. A betűtípust a grafikusra bízom, de illeszkedjen a háttér stílusához. A betűszínt illetően a &nbsp;fekete a standard, ezért ezen nem változtattam. Ha a háttér miatt más betűszín illik a tablóhoz, akkor azt is válassza ki nyugodtan.&nbsp;</p><p>&nbsp;</p>", "font_family": "Grafikusra bízom", "old_school_id": "125", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Ballai Boglárka\\r\\nBalogh Anna\\r\\nBelényesi Nikolett Oxana\\r\\nCaba Daniella\\r\\nDian Damian\\r\\nJámbor Csaba\\r\\nKovács Nóra\\r\\nKozeschnik Emma\\r\\nLassó Dániel\\r\\nMag Zsombor Jenő\\r\\nMokthar Amina\\r\\nNyíri Anna Jázmin\\r\\nÖzbek Arda\\r\\nSávay Szonja Adrienn\\r\\nSchmitt Ruben Alfred\\r\\nZsiros-Lorch Dzsenifer Hilda", "teacher_description": "Réti László igazgató \\r\\nÁrpád Attila igazgatóhelyettes, matematika \\r\\nHolma-Tóth Adrienn osztályfőnök, magyar nyelv és irodalom \\r\\ndr. Balangó László biológia-kémia \\r\\nSzabó Ábel történelem \\r\\nKertész Ferenc angol nyelv\\r\\nHerczeg Eszter magyar nyelv és irodalom, mozgóképkultúra és médiaismeret\\r\\nMajoros László matematika, fizika, digitális kultúra\\r\\nKiss Tímea matematika\\r\\nKomjáthy Katalin angol és német nyelv \\r\\nBiczó Ildikó spanyol nyelv \\r\\nBeregi Nóra angol nyelv \\r\\nLichner Barbara vizuális kultúra \\r\\nTakáts Tamás Henrik állampolgári ismeretek, művészettörténet, földrajz, ének-zene \\r\\nHesser Judit testnevelés \\r\\nClaudia Andrade spanyol nyelv \\r\\nFelcsuti Zita iskolatitkár\\r\\nHeinczinger Márton francia és német nyelv"}	2025-10-25 03:32:26	92	12. B	2022 - 2026
94	94	94	Pasaréti Gimnázium - 12. A 2018 - 2026	4	in_print	t	2025-11-28 05:12:26	2025-11-30 13:39:20	{"uuid": "0b69ef85-5c08-491c-b388-bf89e1e6307b", "color": "#000000", "quote": "Omnia mea mecum porto", "size_id": "5", "category": null, "sort_type": "mindegy", "background": null, "class_name": "12. A", "class_year": "2018 - 2026", "order_form": "order/form/pasareti-gimnazium-2018-2026-12-a-megrendelolap.pdf", "other_file": "order/attachments/pasareti-gimnazium-2018-2026-12-a-otherfile.png", "ai_category": null, "description": "<p>Tartjuk a kapcsolatot :-)</p>", "font_family": "Grafikusra bízom", "old_school_id": "125", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": "Akhinszky Zsombor Imre\\r\\nBánhegyi\\tAlina\\r\\nCsercsa\\tEszter\\r\\nDúl\\tFanni Veronika\\r\\nForró Tamás Bendegúz\\r\\nHauberl Hunor Gábor\\r\\nJankó Virág Berta\\r\\nJankó-Brezovay Nóra\\r\\nKelemen Jázmin Luca\\r\\nKő Bence Tamás\\r\\nKulcsár Ábel\\r\\nLosonczi Ádám Tamás\\r\\nLy Maddox Liam\\r\\nMészáros Mia Lilien\\r\\nProhászka Zsófi\\r\\nS. Nagy Olivér\\r\\nSabjányi Ádám Dávid\\r\\nSzabó Izsák János\\r\\nSzabuni Noora Laura\\r\\nTamási Eszter\\r\\nUngár Mátyás\\r\\nVasas Péter", "teacher_description": "Tanári névsor és a kép sorszáma – Pasaréti Gimnázium 12 B.\\r\\nRéti László igazgató 29.\\r\\nÁrpád Attila igazgatóhelyettes, matematika 24.\\r\\nHolma-Tóth Adrienn osztályfőnök, magyar nyelv és irodalom 45.\\r\\ndr. Balangó László biológia-kémia (tavalyi fotó, de ne legyen olyan sárga az arcbőre)\\r\\nSzabó Ábel történelem 15.\\r\\nKertész Ferenc angol nyelv (tavalyi fotó)\\r\\nHerczeg Eszter 17. magyar nyelv és irodalom, mozgóképkultúra és médiaismeret\\r\\nMajoros László matematika, fizika, digitális kultúra (tavalyi fotó, de ne legyen olyan sárga az arcbőre)\\r\\nKiss Tímea matematika 27.\\r\\nKomjáthy Katalin angol és német nyelv 22. , \\r\\nBiczó Ildikó spanyol nyelv (tavalyi fotó)\\r\\nBeregi Nóra angol nyelv (tavalyi fotó)\\r\\nLichner Barbara vizuális kultúra (tavalyi fotó)\\r\\nTakáts Tamás Henrik Tocy művészettörténet, ének-zene, állampolgári ismeretek, hon- és népismeret, földrajz\\r\\nHesser Judit testnevelés (tavalyi fotó)\\r\\nClaudia Andrade spanyol nyelv\\r\\nFelcsuti Zita iskolatitkár (tavalyi fotó)\\r\\nHeinczinger Márton francia és német nyelv 40. – két képet kér nyomtatva 10 és 38\\r\\nDobos Béla - régi fotó , fekete szalaggal, mert ő sajnos már nincs köztünk"}	2025-11-08 07:10:31	92	12. A	2018 - 2026
90	90	90	Budapest-Fasori Evangélikus Gimnázium - 12. B ???? - 2026	3	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "8cfeb444-59e4-40b1-8ba5-935d56d670ff", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12. B", "class_year": "???? - 2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "139", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	2025-11-06 16:03:22	105	12. B	???? - 2026
92	92	92	Táncsics Mihály Mezőgazdasági Technikum Szakképző és Kollégium - 13.b ???? - 2026	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "11f6f0ae-6c29-4ec9-bf50-23259ab77eca", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "13.b", "class_year": "???? - 2026", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "67", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	35	13.b	???? - 2026
95	95	95	Vak Bottyán János Katolikus Technikum, Gyöngyös - 12.  B ???? - 2025	1	not_started	f	2025-11-28 05:12:26	2025-11-28 13:16:46	{"uuid": "99aca7f1-89ec-4d07-aab3-504d59337b62", "color": "", "quote": null, "size_id": "5", "category": null, "sort_type": "", "background": null, "class_name": "12.  B", "class_year": "???? - 2025", "order_form": null, "other_file": null, "ai_category": null, "description": null, "font_family": "", "old_school_id": "20", "old_status_id": "4", "our_replied_at": null, "ai_category_score": null, "contact_replied_at": null, "student_description": null, "teacher_description": null}	\N	19	12.  B	???? - 2025
\.


--
-- Data for Name: tablo_contacts; Type: TABLE DATA; Schema: public; Owner: photo_stack
--

COPY public.tablo_contacts (id, tablo_project_id, name, email, phone, note, created_at, updated_at, call_count, sms_count, last_contacted_at) FROM stdin;
1	1	Szekula Flóra	floraszekula@gmail.com	36301778708	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
2	2	Mátyás Míra	matyas.mira@arpadgimnazium.hu	36702623527	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
3	3	Károly Virág	karoly.virag.t@arpadgimnazium.hu	36702729708	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
4	4	Vida Matild	vm@arpadgimi.hu	06309693310	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
5	5	László Réka	22blare@arpadgimi.hu	36203387996	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
6	6	Solymár Dávid	davidsolymar8@gmail.com	36301603116	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
7	7	Gergőné Szöllősi Tünde	gszt@arpadgimi.hu	36204986597	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
8	8	Málits Barnabás	malitsbani@gmail.com	36309382945	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
9	9	Bódi Petra	pbodi282@gmail.com	+36304777889	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
10	10	Füredi-Szabó Zsófia	fsz-zsofia@gmail.com	36705440529	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
11	11	Székely Gréta Eszter	szekelygretaeszter@gmail.com	36307167330	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
12	12	Dócs-Zsurkán Mariann	mzsurkan@gmail.com	36706122056	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
13	13	Varga Vanda	lolka0214@gmail.com	36202359326	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
14	14	Andruskó Csenge Anna	csengeandrusko@gmail.com	36202817965	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
15	15	Zala Katalin	zalakatabagoly@gmail.com	36303794027	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
16	16	Szilner Botond	szilnerbotond@gmail.com	36307317310	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
17	17	Farkas Áron	aron.farkas@outlook.com	36301834982	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
18	18	Csernovszki Sarolta	csernovszkisaci@gmail.com	36703592469	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
19	19	V. Szabó Boróka	vsza.boroka@gmail.com	36203511199	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
20	20	Nenkold Zsigmond	nenkoldzsigmond@gmail.com	36702999569	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
21	21	Bauer Hanna	bauerhanka@gmail.com	36205359390	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
22	22	Villányi Emma	emmavillanyi@gmail.com	36702416678	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
23	23	Németh Benedek	nemeth.benedek.endre@gmail.com	36704030424	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
24	24	Bolehovszky Gergő	bolegergo@gmail.com	36703003588	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
25	25	Kiss Emma Júlia	kissemma05@gmail.com	36709042350	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
26	26	Péter-Negro Aténa	negro.athena@gmail.com	36302277783	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
27	27	Bere Ábel	bereabelistvan@gmail.com	36202254634	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
28	28	Szunyogh Péter Dániel	danielszunyogh86@gmail.com	36301111568	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
29	29	Borcsik Mónika	borcsik.monika@budaitechnikum.hu	36205019124	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
30	30	Vígh Florina Léna	vighf06@gmail.com	36703246496	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
31	31	Takács Fruzsina	takacsfruzsina3@gmail.com	36308588650	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
32	32	Hirschler Tamás	hirschler.tamas@gmail.com	36205038903	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
33	33	Cseresznye Milán	cseresznyemilan@gmail.com	36202848696	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
34	34	Nyáry Ádám Mihály	nyaryadam@gmail.com	36306280003	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
35	35	Bánkuti Emma	bankutiemma27@gmail.com	36308742171	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
36	36	Bertók-Bálint Ticián	bbtician9@gmail.com	+36202734339	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
37	37	Jakab Emma	jakabemma2006@gmail.com	36205171045	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
38	38	Csomor Orsolya	orsolyacsomor0518@gmail.com	36204509972	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
39	39	Szeder Janka	szederj-bny-2021@illyes-bors.com	36706041009	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
40	40	Berta Rebeka	bertare123@gmail.com	36304847370	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
41	41	Komáromi Anna Bóra	komaromipannabora@gmail.com	36202672706	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
42	42	Hortobágyi Anna	hortobagyi.anna989@gmail.com	36204362353	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
43	43	Berkovits-Stroh Hanna	bshanna@outlook.com	36202706389	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
44	44	Farvadi Mátyás	farvadimatyas@gmail.com	36307551262	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
45	45	Farkas Liliána	farkasliliana8@gmail.com	36306982661	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
46	46	Jakab Petra	jakab.petra.07@gmail.com	36703065490	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
47	47	Kiss Jagoda Anna	jagoda.anna.kiss@gmail.com	36304643993	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
48	48	Sallai Zoé Elizabet	zoesallai9@gmail.com	36705248149	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
49	49	Belénessy Blanka	belenessy.blanka@kcss.hu	36305547491	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
50	50	VilmányiAndrea Hanna	hanna.vilmanyi@gmail.com	36303665656	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
51	51	Molnár Míra	molnar.mimi.2006@gmail.com	36303412228	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
52	52	Radeczki Jázmin	jazmin00016@gmail.com	36706717816	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
53	53	Turóczi Levente	turoczilevente2007@gmail.com	36703509164	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
54	54	Csontos Róbert	csontosrobika11@gmail.com	36305727227	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
55	55	Kerek Dia	kerekdia11@gmail.com	36709402150	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
56	56	Varró Zsófia	2022_varro.zsofia@madach.org	36304053776	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
57	57	Kovácsné Kóka Marianna	kovacsnekoka.marianna@madach.org	+36309132532	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
58	58	Onder Tamara	ondert@moricz-bp.hu	36302816625	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
59	59	Guller András	gullera@moricz-bp.hu	36309707325	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
60	60	Karvai Kristóf	karvaik@moricz-bp.hu	36309646000	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
61	61	Sóskuti Alíz	soskutia@moricz-bp.hu	36203467729	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
62	62	Garai Péter	garaia@moricz-bp.hu	\N	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
63	63	Köhler-Eötvös Hella	eotvos.hella@gmail.com	36702974468	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
64	64	Bereznai Blanka	bereznai.blanka@gmail.com	36307293971	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
65	65	Dobóczi Dorina	doboczidorina2008@gmail.com	36202317663	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
66	66	Németh Zorka	nemethzorka582@gmail.com	36304900296	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
67	67	Albert Johanna	albertjohanna11@gmail.com	36709071042	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
68	68	Bács Eszter	bestofeszto@gmail.com	36204857554	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
69	69	Márton Viktória Réka	a18mv@dkrmg.hu	36306309587	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
70	70	Böőr Bíborka	boorbibo@gmail.com	36709488111	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
71	71	Szilágyi Barbara	szilagyibarbi08@gmail.com	36702576469	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
72	72	Seres Kata	sereskata08@gmail.com	36304586312	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
73	73	Ferentzi Bálint	balintferentzi@gmail.com	36704336944	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
74	74	Gavalovics Míra	mimigavalovics@gmail.com	36303086732	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
75	75	Vavrik Rella	vavrik.rella.22d@szlgbp.hu	36309051780	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
76	76	Bertók Lili	bertok.lili2007@gmail.com	36202507829	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
77	77	Keller Márton	kellermarton1109@gmail.com	36706761376	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
78	78	Kaló István	kalo.istvan@vbjnet.hu	36702242322	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
79	79	Govrik Péter	govrik.peter@gmail.com	36305172613	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
80	80	Pászti Bálint	paszti.balint2006@gmail.com	36705185408	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
81	81	Polgár Kitti	polgarkitti07@gmail.com	36308467333	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
82	82	Boda Dzsenifer Liliána	bodadzsenifer@gmail.com	36705554317	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
83	83	Weinberger Dorina	dorinaweinberger3@gmail.com	36204542608	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
84	84	Pável Ágnes Napsugár	pavel.agnes@gmail.com	06203961345	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
85	85	German Luca	gluc514@gmail.com	06302612410	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
86	86	Ferger Annamária	ferger.arsz@gmail.com	\N	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
87	87	Mohácsi Katica	mohacsi.katica@gmail.com	\N	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
88	88	Bori Edina	edina.bori@gmail.com	06202213242	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
89	89	Tóth Melinda	toth.melinda@gmail.com	\N	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
90	90	Liling Adél	liling.adele@gmail.com	06702080278	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
91	91	Bitemo Artúr	artur@bitemo.hu	0036308290363	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
92	92	Szalay Péter	szalay.peter@vacimezo.com	36304280046	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
93	93	Holma-Tóth Adrienn	t.toth.adrienn@gmail.com	+36 20 8031 005	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
94	94	Takáts Tamás	tocy@tocy.hu	06303880799	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
95	95	Nagy István Máté	nagyistvanmate20@gmail.com	06301298202	\N	2025-11-28 05:12:26	2025-11-28 05:12:26	0	0	\N
96	98	Nové Ferenc	nove.ferenc@gmail.com	06706328131	\N	2025-11-30 15:14:30	2025-11-30 15:14:30	0	0	\N
97	98	Nové Nové	nove.ferenc+11@gmail.com	06706328131	\N	2025-11-30 18:25:06	2025-11-30 18:25:06	0	0	\N
\.


--
-- Data for Name: tablo_missing_persons; Type: TABLE DATA; Schema: public; Owner: photo_stack
--

COPY public.tablo_missing_persons (id, tablo_project_id, name, local_id, note, created_at, updated_at, "position", type, media_id) FROM stdin;
1	24	Teszt Ember	999	\N	2025-11-30 11:54:10	2025-11-30 11:54:10	0	student	\N
1694	98	Batizi- Pócsi Eszter	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	1	student	\N
2	27	Szabó Orsolya	33901	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	0	teacher	\N
4	27	Gyivicsánné Bakk Marianna	33910	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	1	teacher	\N
6	27	Béla	33948	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	2	teacher	\N
8	27	Juhos Enikő Klára	33909	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	3	teacher	\N
9	27	Tesztelek	33946	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	4	teacher	\N
12	27	Teszt 2	33947	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	5	teacher	\N
13	27	Gaál Marianna	33902	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	6	teacher	\N
15	27	Bartha Beatrix	33903	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	7	teacher	\N
17	27	Ábrahám-Bura Annamária	33904	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	8	teacher	\N
19	27	Mundrusz Máté	33905	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	9	teacher	\N
22	27	Merkely István Keresztély	33912	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	10	teacher	\N
23	27	Mihály Helga Edit	33906	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	11	teacher	\N
25	27	Vrana-Heits Anita	33911	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	12	teacher	\N
26	27	Vencz Zoltán	33915	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	13	teacher	\N
29	27	Wilhelm Mónika	33917	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	14	teacher	\N
31	27	Nyuli Boglárka	33914	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	15	teacher	\N
32	27	Mészáros Vanda	33913	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	16	teacher	\N
34	27	Sági Andrea	33916	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	17	teacher	\N
37	27	Kozma Géza	33907	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	18	teacher	\N
39	27	Nagy Erika	33918	\N	2025-11-30 11:56:48	2025-11-30 12:51:21	19	teacher	\N
1714	98	Palyaga Bálint	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	21	student	\N
1695	98	Berkes Dalma	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	2	student	\N
1696	98	Bernáth Lívia	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	3	student	\N
1697	98	Boda Dzsenifer Liliána	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	4	student	\N
1698	98	Csányi- Nagy Fédra	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	5	student	\N
1699	98	Csaplár Bernadett	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	6	student	\N
1700	98	Csertán Laura	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	7	student	\N
1701	98	Csizmadia László	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	8	student	\N
1702	98	Faragó- Szuhay Márton	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	9	student	\N
1703	98	Fülöp Zoltán	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	10	student	\N
1704	98	Hertelendy Anna	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	11	student	\N
1705	98	Kendrovics László	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	12	student	\N
1706	98	Kéri Máté	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	13	student	\N
1707	98	Laczkó Dávid	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	14	student	\N
1708	98	Lukáts Szófia	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	15	student	\N
1709	98	Madai Csenge	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	16	student	\N
1710	98	Mészáros Nóra	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	17	student	\N
1711	98	Müller Liza	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	18	student	\N
1712	98	Ónodi Liliána Tímea	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	19	student	\N
1713	98	Pál Réka	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	20	student	\N
1715	98	Pan Yi	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	22	student	\N
1716	98	Rostás Máté Magor	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	23	student	\N
1717	98	Ruszányuk Kristóf	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	24	student	\N
1718	98	Szabó Hanna Dóra	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	25	student	\N
1719	98	Szabó Viola	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	26	student	\N
1720	98	Szűcs Bianka	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	27	student	\N
1721	98	Tichy Cintia	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	28	student	\N
1722	98	Tőzsér Cintia Alexa	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	29	student	\N
1723	98	Vartik Réka	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	30	student	\N
1724	98	Vastag Cintia	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	31	student	\N
1725	98	Vinyarszki Ádám Dániel	\N	\N	2025-12-01 05:40:48	2025-12-01 05:40:48	32	student	\N
388	37	Csapó Imre	30731	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	0	teacher	\N
389	37	Antal Máté	30679	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	0	student	\N
390	37	Arany-Kautz Flóra	30686	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	1	student	\N
391	37	Fidesz Ivett	30714	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	1	teacher	\N
392	37	Gesztesi Ildikó	30718	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	2	teacher	\N
393	37	Burkus Lilla Csilla	30688	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	2	student	\N
394	37	Greff Tamás	30720	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	3	teacher	\N
395	37	Dános Szilárd	30692	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	3	student	\N
396	37	Hegedüs Gabriella	30713	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	4	teacher	\N
397	37	Drajkó Bálint	30711	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	4	student	\N
398	37	Jobbágyné Szűcs Tímea	30721	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	5	teacher	\N
399	37	Fábián Mária Zita	30710	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	5	student	\N
400	37	Kosztra Gábor	30719	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	6	teacher	\N
401	37	Gerecs Panna	30685	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	6	student	\N
402	37	Heincz Benedek	30698	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	7	student	\N
403	37	Leányfalviné Fekete Rózsa	30726	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	7	teacher	\N
404	37	Oláh Ferenc	30723	(kérdéses még hogy kép formátumban e)	2025-11-30 13:29:05	2025-11-30 13:29:05	8	teacher	\N
405	37	Herédi Fanni	30691	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	8	student	\N
406	37	Horváth Máté József	30705	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	9	student	\N
407	37	Ottó Katalin	30722	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	9	teacher	\N
408	37	Hugyecz Attila	30697	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	10	student	\N
409	37	Pálmainé Rajki Annamária	30715	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	10	teacher	\N
410	37	Jakab Emma	30683	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	11	student	\N
411	37	Ujhelyiné Rátóti Szilvia Sarolta	30716	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	11	teacher	\N
412	37	Gergely Zsolt	30730	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	12	teacher	\N
413	37	Kalhamer Dávid	30702	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	12	student	\N
414	37	Fördősné Rozmán Edina	30724	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	13	teacher	\N
415	37	Katona Áron Róbert	30689	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	13	student	\N
416	37	Kis Csenge	30700	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	14	student	\N
417	37	Gerendai Márk	30728	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	14	teacher	\N
418	37	Krizsán Balázs	30693	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	15	student	\N
419	37	Jardek Dániel	30729	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	15	teacher	\N
420	37	Kurdi Petra	30694	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	16	student	\N
421	37	Kovács Noémi	30725	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	16	teacher	\N
422	37	Pajorné Menyhárt Mónika	30727	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	17	teacher	\N
423	37	Lénárt Dorina	30701	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	17	student	\N
424	37	Lengyel Benedek	30695	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	18	student	\N
425	37	Dr. Stareczné Kelemen Éva	30717	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	18	teacher	\N
426	37	Majer Luca Emese	30687	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	19	student	\N
427	37	Makrai Bálint	30699	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	20	student	\N
428	37	Mátrai Tamás	30704	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	21	student	\N
429	37	Mészáros Balázs József	30690	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	22	student	\N
430	37	Mészáros Nelli Dorottya	30681	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	23	student	\N
431	37	Molnár Lili	30684	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	24	student	\N
432	37	Pikács Petra	30707	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	25	student	\N
433	37	Pintér Dominik	30680	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	26	student	\N
434	37	Schwarcz Norman	30706	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	27	student	\N
435	37	Sogrik Dorina Kata	30682	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	28	student	\N
436	37	Varga Szabolcs	30708	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	29	student	\N
437	37	Varga Tamara	30696	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	30	student	\N
438	37	Vogel Viktória	30709	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	31	student	\N
439	37	Volentics Emília	30703	\N	2025-11-30 13:29:05	2025-11-30 13:29:05	32	student	\N
440	76	Balázs Gemma	30745	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	0	student	\N
441	76	Gyombolai Gyula	30778	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	0	teacher	\N
442	76	Bertók Lili	30746	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	1	student	\N
443	76	Borosné Felföldi Mária Judit	30777	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	1	teacher	\N
444	76	Ihász István	30798	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	2	teacher	\N
445	76	Bódi Laura	30747	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	2	student	\N
446	76	Bokor Henrik	30748	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	3	student	\N
447	76	Jung Lilla	30800	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	3	teacher	\N
448	76	Furcsa Gábor	30799	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	4	teacher	\N
449	76	Csizmadia Emese	30749	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	4	student	\N
450	76	Bakos Ferenc Andreász	30775	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	5	teacher	\N
451	76	Dancsevics Dominik	30750	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	5	student	\N
452	76	Daub Nadine Rebeka	30751	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	6	student	\N
453	76	Komlósi Attila	30780	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	6	teacher	\N
454	76	Dávid Kamilla	30752	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	7	student	\N
455	76	Bakura József	30776	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	7	teacher	\N
456	76	Vörös Ildikó	30784	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	8	teacher	\N
457	76	Demeter Barbara	30753	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	8	student	\N
458	76	Schwartz Marianna	30783	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	9	teacher	\N
459	76	Herendi Csenge	30754	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	9	student	\N
460	76	Herczeg Flóra	30755	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	10	student	\N
461	76	Jung Tímea	30779	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	10	teacher	\N
462	76	Horváth Boglárka	30756	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	11	student	\N
463	76	Scherrenberg Johannes	30782	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	11	teacher	\N
464	76	Kovács Nóra Emília	30757	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	12	student	\N
465	76	Rostás Balázs	30781	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	12	teacher	\N
466	76	Medgyesi-Lázár Mónika	30801	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	13	teacher	\N
467	76	Kővári Csenge	30758	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	13	student	\N
468	76	Nagy-Benedek Domonkos	30759	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	14	student	\N
469	76	Dudok Dávid	30802	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	14	teacher	\N
470	76	Nagy Boglárka Boróka	30760	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	15	student	\N
471	76	Elbakour Emíra	30803	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	15	teacher	\N
472	76	Nagy Petra	30761	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	16	student	\N
473	76	Orsik Dóra Natasa	30762	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	17	student	\N
474	76	Pallagi Katalin Tímea	30763	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	18	student	\N
475	76	Sarankó Bernadett	30764	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	19	student	\N
476	76	Singer Szimonetta	30765	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	20	student	\N
477	76	Szabó Dorina	30766	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	21	student	\N
478	76	Szűcs Leila Melinda	30767	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	22	student	\N
479	76	Udvarhelyi Luca	30768	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	23	student	\N
480	76	Urbán Dorián Lázár	30769	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	24	student	\N
481	76	Vágó Zsófia	30770	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	25	student	\N
482	76	Vaisz Bendegúz Ferenc	30771	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	26	student	\N
483	76	Varga Eszter	30772	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	27	student	\N
484	76	Vecsei Boróka	30773	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	28	student	\N
485	9	Pataki Marianna	30848	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	0	teacher	\N
486	9	Alt Kornélia	30804	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	0	student	\N
487	9	Asztalos Tamás	30805	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	1	student	\N
488	9	Fucskó Anna	30849	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	1	teacher	\N
489	9	Sóstói Gáborné	30847	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	2	teacher	\N
490	9	Bobák Mirabella	30806	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	2	student	\N
491	9	Zsigriné Zeller Terézia	30826	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	3	teacher	\N
492	9	Bukovinszky Ádám	30807	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	3	student	\N
493	9	Annusné Labancz Márta	30836	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	4	teacher	\N
494	9	Burcsa Anna Amira	30808	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	4	student	\N
495	9	Balog Rita	30839	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	5	teacher	\N
496	9	Csapó Nóra	30809	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	5	student	\N
497	9	Dinh Quoc Tung Tamás	30810	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	6	student	\N
498	9	Bodó Antalné	30834	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	6	teacher	\N
499	9	Fazekas Panni	30811	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	7	student	\N
500	9	Csóka Beáta	30832	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	7	teacher	\N
501	9	Haraszti Krisztián	30812	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	8	student	\N
502	9	Dr. Penyigei Erzsébet	30835	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	8	teacher	\N
503	9	Kassai Csaba	30813	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	9	student	\N
504	9	Gyimesiné Krämer Judit	30830	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	9	teacher	\N
505	9	Kaposiné Bodó Anna	30827	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	10	teacher	\N
506	9	Lázár Dávid	30814	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	10	student	\N
507	9	Matics Marcell	30815	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	11	student	\N
508	9	Kósa Edit	30831	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	11	teacher	\N
509	9	Mihailov Klára	30816	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	12	student	\N
510	9	Kovácsné Ungár Tímea	30837	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	12	teacher	\N
511	9	Nagy Ambrus	30817	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	13	student	\N
512	9	Lisztóczki János	30844	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	13	teacher	\N
513	9	Németh Kristóf	30818	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	14	student	\N
514	9	Lohn Richárd	30842	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	14	teacher	\N
515	9	Perjési Balázs	30819	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	15	student	\N
516	9	Lunczer Ildikó	30845	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	15	teacher	\N
517	9	Srancsik Nóra	30820	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	16	student	\N
518	9	Marton Sándor	30843	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	16	teacher	\N
519	9	Südi Katalin	30821	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	17	student	\N
520	9	Németh Máté	30829	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	17	teacher	\N
521	9	Orosz Ágnes	30833	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	18	teacher	\N
522	9	Székács Laura Hanna	30823	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	18	student	\N
523	9	Szepesi Boróka	30822	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	19	student	\N
524	9	Papné Honti Mária	30828	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	19	teacher	\N
525	9	Rab Dóra	30846	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	20	teacher	\N
526	9	Till Petra Réka	30824	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	20	student	\N
527	9	Soha József	30841	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	21	teacher	\N
528	9	Vizi Lilla	30825	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	21	student	\N
529	9	Suskó-Csécsi Petra	30840	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	22	teacher	\N
530	9	Wiedemann Krisztina	30838	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	23	teacher	\N
531	59	Bíró Borbála	30850	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	0	student	\N
532	59	Bärnkopf Bence	30877	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	0	teacher	\N
533	59	Bodolai Jázmin Anna	30851	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	1	student	\N
534	59	Tassi Balázs	30878	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	1	teacher	\N
535	59	Borbély Anna Borbála	30852	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	2	student	\N
536	59	Poór-Bagyinszki Diána	30879	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	2	teacher	\N
537	59	Bata Dániel	30888	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	3	teacher	\N
538	59	Csépai Benedek	30853	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	3	student	\N
539	59	Csepely Bálint	30854	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	4	student	\N
540	59	Bereczki Réka	30895	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	4	teacher	\N
541	59	Csillag Miklós	30855	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	5	student	\N
542	59	Dr. Osváth László	30896	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	5	teacher	\N
543	59	Csobádi Nelli	30856	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	6	student	\N
544	59	Gábor Attila	30882	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	6	teacher	\N
545	59	Felső Gréta	30857	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	7	student	\N
546	59	Gubiczáné Gombár Csilla	30885	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	7	teacher	\N
547	59	György Dániel	30881	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	8	teacher	\N
548	59	Fonyó Adám	30858	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	8	student	\N
549	59	Galló Noémi Léna	30859	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	9	student	\N
550	59	Hambuch Mátyás	30894	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	9	teacher	\N
551	59	Guller András	30860	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	10	student	\N
552	59	Herczegh Gabriella	30897	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	10	teacher	\N
553	59	Gyurkovics Bálint	30861	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	11	student	\N
554	59	Institórisz László	30883	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	11	teacher	\N
555	59	Horváth Kristóf Nándor	30862	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	12	student	\N
556	59	Knyihár Amarilla	30889	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	12	teacher	\N
557	59	Mészáros Mátyás	30898	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	13	teacher	\N
558	59	Jeles Dániel Péter	30863	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	13	student	\N
559	59	Nagy Anita	30893	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	14	teacher	\N
560	59	Kérdő Áron András	30864	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	14	student	\N
561	59	Lombard-Eszes Marion Amélie	30865	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	15	student	\N
562	59	Palkovics Krisztina	30892	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	15	teacher	\N
563	59	Mészáros Flóra Margit	30866	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	16	student	\N
564	59	Paulikné Reicher Andrea	30884	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	16	teacher	\N
565	59	Somodi Zoltán	30880	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	17	teacher	\N
566	59	Mészáros Hunor Gábor	30867	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	17	student	\N
567	59	Nagy-Horváth Csaba	30868	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	18	student	\N
568	59	Spolarich Tünde	30886	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	18	teacher	\N
569	59	Quintavalle Fabio	30869	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	19	student	\N
570	59	Szabó Klára	30887	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	19	teacher	\N
571	59	Szalai Kornélné	30899	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	20	teacher	\N
572	59	Török Titusz Aurél	30870	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	20	student	\N
573	59	Szűcs Eszter	30891	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	21	teacher	\N
574	59	Toth Fédra Alexandra	30871	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	21	student	\N
575	59	Valkai Borbála	30890	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	22	teacher	\N
576	59	Üveges Kornél Zoltán	30872	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	22	student	\N
577	59	Váradi Zsombor	30873	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	23	student	\N
578	59	Zana Zoé Martina	30874	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	24	student	\N
579	59	Zsiga Emese Dorka	30875	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	25	student	\N
580	59	Pesti Dalma	30876	\N	2025-11-30 13:29:06	2025-11-30 13:29:06	26	student	\N
581	75	Azurák Hanna	31125	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	0	student	\N
582	75	Adamis Bence	31121	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	0	teacher	\N
583	75	Audrey Déry	31117	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	1	teacher	\N
584	75	Balázs-Nagy Zsófia	31126	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	1	student	\N
585	75	Balogh Dorka	31127	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	2	student	\N
586	75	Bajzáth Adrienn	31103	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	2	teacher	\N
587	75	Bárdos Janka	31128	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	3	student	\N
588	75	Balanyi Rita	31100	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	3	teacher	\N
589	75	Baumann Csaba Bálint	31129	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	4	student	\N
590	75	Bartháné Ábrahám Katalin	31112	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	4	teacher	\N
591	75	Berecz Ákos	31130	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	5	student	\N
592	75	Darabánt Emese	31119	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	5	teacher	\N
593	75	Dankó Noémi Kriszta	31131	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	6	student	\N
594	75	Szendrei Péter	31123	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	6	teacher	\N
595	75	Davini Sofia	31132	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	7	student	\N
596	75	Lissák Bertalan	31124	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	7	teacher	\N
597	75	Dézsi Viktória Csenge	31133	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	8	student	\N
598	75	Földesi Dávid	31095	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	8	teacher	\N
599	75	Dudás-Györki Csenge	31134	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	9	student	\N
600	75	Deák Ferenc	31104	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	9	teacher	\N
601	75	Grandpierre Krisztián	31135	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	10	student	\N
602	75	Fekete Richárd	31115	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	10	teacher	\N
603	75	Fodor Sára	31116	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	11	teacher	\N
604	75	Gulyás Liliána Olívia	31136	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	11	student	\N
605	75	Grund Ágnes	31109	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	12	teacher	\N
606	75	Gyimesi Eszter Jázmin	31137	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	12	student	\N
607	75	Heimpold Eliza Gréta	31138	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	13	student	\N
608	75	Halász Judit	31098	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	13	teacher	\N
609	75	Hujber Szabolcs	31113	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	14	teacher	\N
610	75	Kollár Milán	31139	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	14	student	\N
611	75	Koppány Csenge	31140	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	15	student	\N
612	75	Kneusel Szilvia	31096	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	15	teacher	\N
613	75	Köteles-Hompoth Hunor	31141	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	16	student	\N
614	75	Krasnyánszki Dóra	31106	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	16	teacher	\N
615	75	Lovas Erika	31118	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	17	teacher	\N
616	75	Lautner Erik	31142	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	17	student	\N
617	75	Magyar Antónia	31143	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	18	student	\N
618	75	Nagy Bendegúz	31110	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	18	teacher	\N
619	75	Nagy Mónika	31107	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	19	teacher	\N
620	75	Mandl Edina Maja	31144	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	19	student	\N
621	75	Nagy Nóra Emília	31145	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	20	student	\N
622	75	Németh Szilvia	31108	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	20	teacher	\N
623	75	Orbán Angelika	31114	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	21	teacher	\N
624	75	Nagy Nóra Eszter	31146	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	21	student	\N
625	75	Nemes Gerda	31147	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	22	student	\N
626	75	Péteri Zsuzsanna	31122	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	22	teacher	\N
627	75	Pók Tímea	31101	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	23	teacher	\N
628	75	Nossack Martin	31148	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	23	student	\N
629	75	Rábai János	31099	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	24	teacher	\N
630	75	Nyitrai Nóra Zsuzsanna	31149	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	24	student	\N
631	75	Pénzes Panna Virág	31150	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	25	student	\N
632	75	Szabados Péter	31105	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	25	teacher	\N
633	75	Shen Xin Lei	31151	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	26	student	\N
634	75	Szolyka Alina Éva	31120	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	26	teacher	\N
635	75	Simon Anna Júlia	31152	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	27	student	\N
636	75	Tandory Gábor	31111	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	27	teacher	\N
637	75	Somodi Lili	31153	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	28	student	\N
638	75	Teremy Krisztina	31102	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	28	teacher	\N
639	75	Szabó Örs Áron	31154	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	29	student	\N
640	75	Will Dickerson	31097	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	29	teacher	\N
641	75	Székely Imola	31155	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	30	student	\N
642	75	Szőke Anna Sára	31156	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	31	student	\N
643	75	Tóth Botond	31157	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	32	student	\N
644	75	Truong Ngoc Vianh	31158	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	33	student	\N
645	75	Varga Boróka	31159	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	34	student	\N
646	75	Varga Kristóf Levente	31160	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	35	student	\N
647	75	Vavrik Rella	31161	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	36	student	\N
648	75	Végh Zsófia	31162	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	37	student	\N
649	75	Vörös Klára	31163	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	38	student	\N
650	75	Wong Ting Yi	31164	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	39	student	\N
651	31	Balogh Laura	31050	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	0	student	\N
652	31	Bozsaky Csaba	31084	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	0	teacher	\N
653	31	Balogh Máté	31051	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	1	student	\N
654	31	Dombi Csilla	31088	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	1	teacher	\N
655	31	Barna Olivér	31052	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	2	student	\N
656	31	Garancsine Ágnes	31080	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	2	teacher	\N
657	31	Berecz Nándor	31053	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	3	student	\N
658	31	Gazdag Izabella	31091	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	3	teacher	\N
659	31	Pozsgai Petra	31094	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	4	teacher	\N
660	31	Bordás Bence	31054	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	4	student	\N
661	31	Bordás Gergő	31055	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	5	student	\N
662	31	Seller Attila	31076	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	5	teacher	\N
663	31	Boross Levente	31056	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	6	student	\N
664	31	Barta Andrea	31078	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	6	teacher	\N
665	31	Czéh Eugénia	31057	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	7	student	\N
666	31	Veresné-Dongó Katalin	31077	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	7	teacher	\N
667	31	Csuti Péter	31058	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	8	student	\N
668	31	Katona Beáta	31085	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	8	teacher	\N
669	31	Dinnyés Karina	31059	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	9	student	\N
670	31	Krischner Zita	31079	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	9	teacher	\N
671	31	Domaniczky Olivér	31060	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	10	student	\N
672	31	Lackóné Kiss Ágnes	31087	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	10	teacher	\N
673	31	Finta Balázs	31061	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	11	student	\N
674	31	Mednyászky Tünde	31092	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	11	teacher	\N
675	31	Nagy Éva	31093	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	12	teacher	\N
676	31	Hovancsik Kitti	31062	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	12	student	\N
677	31	Kárpáti Miklós	31063	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	13	student	\N
678	31	Pisák Ildikó	31086	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	13	teacher	\N
679	31	Kassai Csenge	31064	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	14	student	\N
680	31	Puha Zoltán	31083	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	14	teacher	\N
681	31	Renáta Hentes-Vigh	31089	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	15	teacher	\N
682	31	Kiss Dániel	31065	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	15	student	\N
683	31	Kutor Petra	31066	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	16	student	\N
684	31	Tatai Beáta	31081	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	16	teacher	\N
685	31	Kovács Katalin Erzsébet	31067	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	17	student	\N
686	31	Végh Andrea	31082	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	17	teacher	\N
687	31	Modli Zsófia Krisztina	31068	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	18	student	\N
688	31	Marton-Sugár Klára	31075	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	18	teacher	\N
689	31	Szabó Szelina	31069	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	19	student	\N
690	31	Pribék Mihály	31090	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	19	teacher	\N
691	31	Takács Fruzsina	31070	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	20	student	\N
692	31	Thuránszky Panna	31071	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	21	student	\N
693	31	Toman Hanna	31072	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	22	student	\N
694	31	Trinfa Johanna	31073	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	23	student	\N
695	31	Vanyek Bianka	31074	\N	2025-11-30 13:29:07	2025-11-30 13:29:07	24	student	\N
696	14	Varga Zsuzsanna	31298	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	0	teacher	\N
697	14	Altsach Ákos	31266	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	0	student	\N
698	14	Andruskó Csenge Anna	31267	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	1	student	\N
699	14	Borsányi Iván	31301	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	1	teacher	\N
700	14	Bertalan Richárd	31268	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	2	student	\N
701	14	Gyügyei Katalin	31292	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	2	teacher	\N
702	14	Czoch Levente András	31269	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	3	student	\N
703	14	Kálmán Nóra	31299	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	3	teacher	\N
704	14	Dósa Benedek	31270	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	4	student	\N
705	14	Konopás Attila	31296	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	4	teacher	\N
706	14	Erdei Viktória	31271	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	5	student	\N
707	14	Kálnainé Gyarmati Klára	31291	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	5	teacher	\N
708	14	Erdélyi Noel Krisztián	31272	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	6	student	\N
709	14	Solymosi Csilla	31290	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	6	teacher	\N
710	14	Menczler Ágnes	31289	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	7	teacher	\N
711	14	Gál Benjámin	31273	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	7	student	\N
712	14	Gergely Janka	31274	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	8	student	\N
713	14	Mojzsis Andrea	31293	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	8	teacher	\N
714	14	Kollár Milán	31275	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	9	student	\N
715	14	Oszaczkiné Szammer Beáta	31297	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	9	teacher	\N
716	14	Kovács Viktória	31276	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	10	student	\N
717	14	Szemán Marietta	31300	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	10	teacher	\N
718	14	Szilágyiné Bubenka Ildikó	32974	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	11	teacher	\N
719	14	Krupa Szabolcs Lehel	31277	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	11	student	\N
720	14	Vollai Dóra	31295	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	12	teacher	\N
721	14	Kukel Flóra	31278	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	12	student	\N
722	14	Wachler Viktor	31294	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	13	teacher	\N
723	14	Lieszkovszki Dávid	31279	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	13	student	\N
724	14	Papp Noémi Lilla	31280	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	14	student	\N
725	14	Pető Dominik Martin	31281	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	15	student	\N
726	14	Révész Hanna	31282	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	16	student	\N
727	14	Simkó Máté	31283	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	17	student	\N
728	14	Szedlár Márk	31284	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	18	student	\N
729	14	Takács Boglárka Zsófia	31285	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	19	student	\N
730	14	Uhlár Márkus	31286	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	20	student	\N
731	14	Vincze Róbert	31287	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	21	student	\N
732	14	Weiger Valentin	31288	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	22	student	\N
733	39	Burián Hana Virginia	31482	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	0	teacher	\N
734	39	Árpádi Júlia Karen	31322	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	0	student	\N
735	39	Banitz Barbara	31307	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	1	student	\N
736	39	Doleviczényi Mónika	31479	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	1	teacher	\N
737	39	Bedő Bendegúz	31309	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	2	student	\N
738	39	Füleki Zsombor	31484	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	2	teacher	\N
739	39	Borsós Ábel	31302	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	3	student	\N
740	39	Fülöp János	31483	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	3	teacher	\N
741	39	Gruberné Szilágyi Ágota	31488	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	4	teacher	\N
742	39	Buzás Emma	31316	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	4	student	\N
743	39	Inges Zsófia	31490	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	5	teacher	\N
744	39	Buzás Marcell	31326	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	5	student	\N
745	39	Bene Tünde	31478	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	6	teacher	\N
746	39	Csanády Boldizsár	31311	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	6	student	\N
747	39	Csébfalvi Hanna	31320	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	7	student	\N
748	39	Kapusy Péter	31485	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	7	teacher	\N
749	39	Csikós Tünde	31334	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	8	student	\N
750	39	Károly Ildikó	31489	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	8	teacher	\N
751	39	Dakó Zalán	31337	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	9	student	\N
752	39	Keresztes Emilia	31493	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	9	teacher	\N
753	39	Keresztes Miklós	31487	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	10	teacher	\N
754	39	Faragó Ágoston	31303	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	10	student	\N
755	39	Fiedler Gusztáv	31319	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	11	student	\N
756	39	Lukácsiné Lehota Edit	31495	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	11	teacher	\N
757	39	Fogarasi Áron	31305	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	12	student	\N
758	39	Péter András Levente	31492	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	12	teacher	\N
759	39	Szakálné Gulyás Katalin	31481	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	13	teacher	\N
760	39	Hermán Csongor	31314	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	13	student	\N
761	39	Szép Adrienn	31486	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	14	teacher	\N
762	39	Horváth Botond	31312	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	14	student	\N
763	39	Torma Rita	31480	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	15	teacher	\N
764	39	Horváth Szelina	31333	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	15	student	\N
765	39	Tóth Pozsonyi Enikő	31494	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	16	teacher	\N
766	39	Hősei Simon	31332	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	16	student	\N
767	39	Hreuss Máté	31328	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	17	student	\N
768	39	Vadász András	31491	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	17	teacher	\N
769	39	Johnny	31496	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	18	teacher	\N
770	39	Illy Ákos	31304	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	18	student	\N
771	39	Kápolnai Kamilla	31323	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	19	student	\N
772	39	Somogyi Zsófia	31477	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	19	teacher	\N
773	39	Kiss Botond	31313	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	20	student	\N
774	39	Köteles Flóra	31317	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	21	student	\N
775	39	Kovács Áron Dániel	31324	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	22	student	\N
776	39	Mayer Barna	31308	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	23	student	\N
777	39	Nagy-Jávori Zalán	31338	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	24	student	\N
778	39	Oláh Márton	31327	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	25	student	\N
779	39	Puskás Luca	31325	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	26	student	\N
780	39	Pusztai Boglárka	31310	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	27	student	\N
781	39	Reményi Franciska	31331	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	28	student	\N
782	39	Rolly Dalma	31315	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	29	student	\N
783	39	Sárközi Flóra	31318	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	30	student	\N
784	39	Sulina Orsolya	31330	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	31	student	\N
785	39	Szeder Janka	31321	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	32	student	\N
786	39	Szekszárdi Máté	31329	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	33	student	\N
787	39	Tóth Violetta	31336	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	34	student	\N
788	39	Varsányi Virág	31335	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	35	student	\N
789	39	Vinkovits Artúr	31306	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	36	student	\N
790	52	Bassay Klára	31383	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	0	teacher	\N
791	52	Berta Nikoletta Boglárka	31358	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	1	student	\N
792	52	Boros Réka	31385	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	1	teacher	\N
793	52	Búzády Lídia	31359	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	2	student	\N
794	52	Csöke Olivér Péterné	31386	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	2	teacher	\N
795	52	Guzmann Katalin	31398	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	3	teacher	\N
796	52	Búzás Bence	31360	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	3	student	\N
797	52	Csengeri Bíborka	31361	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	4	student	\N
798	52	Kiszály E. Anna	31396	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	4	teacher	\N
799	52	Dóczi Krisztina	31395	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	5	teacher	\N
800	52	Csizmazia Vanda Opál	31362	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	5	student	\N
801	52	Kolbása Mária	31397	\N	2025-11-30 13:29:08	2025-11-30 13:29:08	6	teacher	\N
802	52	Emődi-Varga Fruzsina	31363	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	6	student	\N
803	52	Hazenfratz Alexandra Gabriella	31364	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	7	student	\N
804	52	Grábits Ágota	31382	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	7	teacher	\N
805	52	Hajnalné Márta Anita	31387	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	8	teacher	\N
806	52	Hollós Lilla	31365	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	8	student	\N
807	52	Kanics Márta	31388	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	9	teacher	\N
808	52	Ibolya Zsanna Bettina	31366	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	9	student	\N
809	52	Kaszás Dóra	31389	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	10	teacher	\N
810	52	Kálmán Nóra Gabriella	31367	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	10	student	\N
811	52	Kovácsné Piros Gizella	31394	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	11	teacher	\N
812	52	Kreskai Rebeka	31368	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	11	student	\N
813	52	Lencsér Dalma	31369	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	12	student	\N
814	52	Molnár Kinga	31390	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	12	teacher	\N
815	52	Oláh Anita	31391	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	13	teacher	\N
816	52	Majtász-Susla Nelli	31370	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	13	student	\N
817	52	Monori Fanni	31371	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	14	student	\N
818	52	Sneff Szilárd	31392	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	14	teacher	\N
819	52	Stark Tibor	31384	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	15	teacher	\N
820	52	Nagy Adrienn	31372	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	15	student	\N
821	52	Nagy Bettina Vivien	31373	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	16	student	\N
822	52	Szóda Zsuzsanna	31393	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	16	teacher	\N
823	52	Nagy Janka Panka	31374	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	17	student	\N
824	52	Radeczki Jázmin	31375	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	18	student	\N
825	52	Ritter Alexandra	31376	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	19	student	\N
826	52	Sallai Fruzsina	31377	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	20	student	\N
827	52	Sáska Natália	31378	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	21	student	\N
828	52	Serényi Dorottya	31379	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	22	student	\N
829	52	Szórádi Eszter	31380	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	23	student	\N
830	52	Ürögi Lívia	31381	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	24	student	\N
831	72	Lázár Tibor	31436	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	0	teacher	\N
832	72	Adorján Kristóf Milán	31399	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	0	student	\N
833	72	Baráz Kornél	31400	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	1	student	\N
834	72	Dr Borbás Réka	31437	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	1	teacher	\N
835	72	Kovács Péter	31438	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	2	teacher	\N
836	72	Blum Blanka	31401	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	2	student	\N
837	72	Abuczki Erika	31464	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	3	teacher	\N
838	72	Csáki Ákos	31402	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	3	student	\N
839	72	Dobó-Nagy Mátyás Pál	31403	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	4	student	\N
840	72	Babus Zoltán	31446	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	4	teacher	\N
841	72	Bartháné Nagy Katalin	31469	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	5	teacher	\N
842	72	Garay Borbála Zsuzsanna	31404	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	5	student	\N
843	72	Goddard Marcell	31405	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	6	student	\N
844	72	Berke Ildikó	31445	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	6	teacher	\N
845	72	Haár Gordon	31406	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	7	student	\N
846	72	Bóka Gábor	31472	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	7	teacher	\N
847	72	Harkai Emma	31407	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	8	student	\N
848	72	Csepregi-Horváth Zsófia	31451	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	8	teacher	\N
849	72	Dankowsky Anna Zóra	31440	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	9	teacher	\N
850	72	Hegyi Márton	31408	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	9	student	\N
851	72	Dörögdi József	31471	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	10	teacher	\N
852	72	Horváth Ábel Nándor	31409	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	10	student	\N
853	72	Kámán Domonkos	31410	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	11	student	\N
854	72	Dr Bajkó Ildikó	31441	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	11	teacher	\N
855	72	Dr Kiss Gabriella	31474	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	12	teacher	\N
856	72	Kincses Milán	31411	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	12	student	\N
857	72	Király Samu	31412	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	13	student	\N
858	72	Dr Sumi Ildikó	31443	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	13	teacher	\N
859	72	Forgó Levente	31466	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	14	teacher	\N
860	72	Kiss Gergely	31413	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	14	student	\N
861	72	Forgóné Szabó Zsuzsanna	31470	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	15	teacher	\N
862	72	Kovács Benedek	31414	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	15	student	\N
863	72	Forrás Péter	31453	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	16	teacher	\N
864	72	Li Xiang	31415	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	16	student	\N
865	72	Mező Marcell Gyula	31416	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	17	student	\N
866	72	Fruchter Diána	31476	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	17	teacher	\N
867	72	Fürész Blanka	31473	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	18	teacher	\N
868	72	Mihályi Olivér	31417	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	18	student	\N
869	72	Mura István	31418	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	19	student	\N
870	72	Gaár Orsolya	31461	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	19	teacher	\N
871	72	Halek Tamás	31439	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	20	teacher	\N
872	72	Németh Barnabás	31419	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	20	student	\N
873	72	Nguyen Ha Anh	31420	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	21	student	\N
874	72	Horváthné Gődény Judit	31447	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	21	teacher	\N
875	72	Hős Csilla	31448	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	22	teacher	\N
876	72	Prácser András	31421	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	22	student	\N
877	72	Inokai Máté	31457	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	23	teacher	\N
878	72	Seres Kata	31422	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	23	student	\N
879	72	Simon Vince	31423	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	24	student	\N
880	72	Jahoda Anna	31468	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	24	teacher	\N
881	72	Jakab Edit	31475	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	25	teacher	\N
882	72	Szánthó Regina Dorka	31424	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	25	student	\N
883	72	Szél Márton	31425	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	26	student	\N
884	72	Juhász István	31460	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	26	teacher	\N
885	72	Kelemen Gabriella	31456	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	27	teacher	\N
886	72	Szlovák Tímea	31426	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	27	student	\N
887	72	Kempl Klára	31452	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	28	teacher	\N
888	72	Szomolai Szabolcs	31427	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	28	student	\N
889	72	Szűcs Réka	31428	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	29	student	\N
890	72	Kósa Zsolt	31454	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	29	teacher	\N
891	72	Tong Shuyu	31429	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	30	student	\N
892	72	Kovács-Veres Tamás	31467	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	30	teacher	\N
893	72	Magyar Zsolt	31459	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	31	teacher	\N
894	72	Turán Lilla	31430	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	31	student	\N
895	72	Ulrich Gábor	31431	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	32	student	\N
896	72	Miklós Zoltán	31442	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	32	teacher	\N
897	72	Vadas János Bendegúz	31432	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	33	student	\N
898	72	Porkoláb Panna Klára	31463	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	33	teacher	\N
899	72	Végh Albert Vince	31433	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	34	student	\N
900	72	Ruszina Mónika	31449	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	34	teacher	\N
901	72	Seregély Ildikó	31444	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	35	teacher	\N
902	72	Zhang Jiayi	31434	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	35	student	\N
903	72	Szabó András	31455	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	36	teacher	\N
904	72	Szatmáry Zsolt	31462	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	37	teacher	\N
905	72	Tamás Beáta	31465	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	38	teacher	\N
906	72	Tugyi Tímea	31458	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	39	teacher	\N
907	72	Vimlátiné Kálmán Judit	31450	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	40	teacher	\N
908	72	Szecsődi Tamás	31435	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	41	teacher	\N
909	93	Claudia Andrade	31573	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	2	teacher	\N
910	13	Kincses Katalin	31867	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	0	teacher	\N
911	13	Horváth Antal	31868	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	1	teacher	\N
912	13	Török Anna Hajnalka	31869	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	2	teacher	\N
913	13	Kereskényi Balázs	31870	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	3	teacher	\N
914	13	Kenéz Anita	31871	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	4	teacher	\N
915	13	Marján Ibolya	31872	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	5	teacher	\N
916	13	Dr Markóczi Mária	31873	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	6	teacher	\N
917	13	Kovács Ákos	31874	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	7	teacher	\N
918	13	Szilágyiné Bubenka Ildikó Erika	31875	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	8	teacher	\N
919	13	Rácz Róbert	31876	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	9	teacher	\N
920	13	Németh Éva	31877	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	10	teacher	\N
921	13	Kálnainé Gyarmati Klára	31878	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	11	teacher	\N
922	13	Solymosi Csilla	31879	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	12	teacher	\N
923	13	Menczler Ágnes	31880	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	13	teacher	\N
924	13	Török István János	31881	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	14	teacher	\N
925	13	Borsányi Iván Tamás	31882	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	15	teacher	\N
926	13	Juhász Gabriella	31582	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	20	student	\N
927	13	Zachar Zita	31594	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	30	student	\N
928	13	Maróti Ramóna Liza	31586	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	40	student	\N
929	13	Nagy Renáta	31589	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	50	student	\N
930	13	Botos Zsófia	31578	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	60	student	\N
931	13	Varga Vanda	31592	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	70	student	\N
932	13	Mitasz Laura Réka	31587	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	80	student	\N
933	13	Szabados Zalán Nimród	31590	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	90	student	\N
934	13	Nagy Alma	31588	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	100	student	\N
935	13	Balga Krisztián	31576	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	110	student	\N
936	13	Fűzik Dániel	31581	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	120	student	\N
937	13	Becsei Balázs	31577	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	130	student	\N
938	13	Kis Kiara Alexandra	31583	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	140	student	\N
939	13	Varga Vanessza	31593	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	150	student	\N
940	13	Csata Boglárka	31580	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	160	student	\N
941	13	Magyar Zoltán Csongor	31585	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	170	student	\N
942	13	Valentics Kamilla Flóra	31591	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	180	student	\N
943	13	Csancsár Máté Levente	31579	\N	2025-11-30 13:29:09	2025-11-30 13:29:09	190	student	\N
944	22	Buti Izabella	31627	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	0	student	\N
945	22	Kovács Noémi	32971	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	0	teacher	\N
946	22	Ambrusné Berencz Zsuzsanna	31665	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	0	teacher	\N
947	22	Bartha Gábor	31650	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	1	teacher	\N
948	22	Fördősné Rozmán Edina	32972	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	1	teacher	\N
949	22	Fricsfalusi Zsuzsanna	31628	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	1	student	\N
950	22	Halász Vivien Vanda	31629	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	2	student	\N
951	22	Gaál Tamara	31663	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	2	teacher	\N
952	22	Gergely Zsolt	32973	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	2	teacher	\N
953	22	Kárpáti Bálint	31630	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	3	student	\N
954	22	Horvath Ibolya	31661	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	3	teacher	\N
955	22	Kollár Dávid	31631	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	4	student	\N
956	22	Bécsi Szilvia	31647	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	4	teacher	\N
957	22	Koncz Gabriella Petra	31632	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	5	student	\N
958	22	Ilauszkyné Varga Enikő	31646	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	5	teacher	\N
959	22	Kószó Álmos Levente	31633	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	6	student	\N
960	22	Kállay Katalin	31648	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	6	teacher	\N
961	22	Kürtösi Hanna Cintia	31634	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	7	student	\N
962	22	Liszonyi Gábor	31649	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	7	teacher	\N
963	22	Kurucz András	31635	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	8	student	\N
964	22	Huszti Lajos	31651	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	8	teacher	\N
965	22	Lukács Kata	31636	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	9	student	\N
966	22	Husztiné Varga Klára	31652	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	9	teacher	\N
967	22	Molnár Angéla	31637	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	10	student	\N
968	22	Istók Balázs	31653	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	10	teacher	\N
969	22	Szűcs Ágnes	31654	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	11	teacher	\N
970	22	Nagy Brigitta	31638	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	11	student	\N
971	22	Nagy Dóra	31639	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	12	student	\N
972	22	Kérdő Krisztina	31664	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	12	teacher	\N
973	22	Marcali Etelka	31662	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	13	teacher	\N
974	22	Sallai Carlos	31640	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	13	student	\N
975	22	Schiller Ágnes	31668	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	14	teacher	\N
976	22	Soós Luna	31641	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	14	student	\N
977	22	Sütőné Seres Adrienn	31655	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	15	teacher	\N
978	22	Tahon Larina	31642	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	15	student	\N
979	22	Tizedes Hanna	31643	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	16	student	\N
980	22	Szilágyi Anna	31667	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	16	teacher	\N
981	22	Villányi Emma	31644	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	17	student	\N
982	22	Szoboszlai Éva	31669	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	17	teacher	\N
983	22	Vizin Vanda	31645	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	18	student	\N
984	22	Szőlősi Krisztina	31656	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	18	teacher	\N
985	22	Sztaskó Richárd	31657	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	19	teacher	\N
986	22	Tatorján Dorottya	31658	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	20	teacher	\N
987	22	Tóth Andrea	31666	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	21	teacher	\N
988	22	Valló Gábor	31660	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	22	teacher	\N
989	22	Timkóné Szatmár Éva	31659	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	23	teacher	\N
990	54	Andrényi Cintia	31670	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	0	student	\N
991	54	Benicsek Mihály	31701	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	0	teacher	\N
992	54	Berki Áron Zoltán	31671	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	1	student	\N
993	54	Boda Mária	31702	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	1	teacher	\N
994	54	Bethlen Áron Becse	31672	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	2	student	\N
995	54	Fockter Zoltán	31703	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	2	teacher	\N
996	54	Bodor Péter Attila	31673	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	3	student	\N
997	54	Horváthné Strommer Éva	31704	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	3	teacher	\N
998	54	Burján Hajnalka	31674	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	4	student	\N
999	54	Horváth Edit	32875	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	4	teacher	\N
1000	54	Csontos Róbert	31675	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	5	student	\N
1001	54	Steidl Levente	32876	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	5	teacher	\N
1002	54	Lakatos-Tombácz Ádám	32877	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	6	teacher	\N
1003	54	Domján Zsigmond László	31676	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	6	student	\N
1004	54	Filó Eszter	31677	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	7	student	\N
1005	54	Tóth-Szabó Júlia	31710	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	7	teacher	\N
1006	54	Kenderessy Tibor	31705	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	8	teacher	\N
1007	54	Forgó Vince	31678	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	8	student	\N
1008	54	Kuruczné Vágási Szilvia	31706	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	9	teacher	\N
1009	54	Gőgh Dóra	31679	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	9	student	\N
1010	54	Lámfalusi Réka	31707	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	10	teacher	\N
1011	54	Hegedűs Dóra	31680	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	10	student	\N
1012	54	Péntek Attiláné	31708	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	11	teacher	\N
1013	54	Horváth Lóránt Gergely	31681	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	11	student	\N
1014	54	Horváth-Konrád Panna	31682	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	12	student	\N
1015	54	Strenner Anita	31709	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	12	teacher	\N
1016	54	Kálmán Zsombor	31683	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	13	student	\N
1017	54	Kántor Lilla	31684	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	14	student	\N
1018	54	Dudás-Kis István Vajk	31685	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	15	student	\N
1019	54	Kovács Barnabás	31686	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	16	student	\N
1020	54	Kovács Patrik	31687	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	17	student	\N
1021	54	Kristóf Fanni	31688	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	18	student	\N
1022	54	Kummer Bálint	31689	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	19	student	\N
1023	54	Lukács Zsófia Leila	31690	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	20	student	\N
1024	54	Majer Blanka	31691	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	21	student	\N
1025	54	Máté Petra	31692	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	22	student	\N
1026	54	Mészáros Luca Liana	31693	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	23	student	\N
1027	54	Németh Csaba Dengizik	31694	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	24	student	\N
1028	54	Niedermüller Anna	31695	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	25	student	\N
1029	54	Somloí Dominik	31696	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	26	student	\N
1030	54	Somlyai Botond	31697	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	27	student	\N
1031	54	Tóth Tamara Bernadett	31698	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	28	student	\N
1032	54	Tódor Tamás	31699	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	29	student	\N
1033	54	Villányi Bendegúz Károly	31700	\N	2025-11-30 13:29:10	2025-11-30 13:29:10	30	student	\N
1034	78	Barta Máté	31711	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	0	student	\N
1035	78	Atya még nem tudjuk melyik - plébános	31734	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	0	teacher	\N
1036	78	Benyovszky Péter	31735	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	0	teacher	\N
1037	78	Bencze Tamás	31712	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	1	student	\N
1038	78	Bencsik András	31713	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	2	student	\N
1039	78	Borbás József	31736	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	2	teacher	\N
1040	78	Tolmayerné Borbély Zsuzsanna	31739	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	2	teacher	\N
1041	78	Besze Gergő	31714	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	3	student	\N
1042	78	Hevér Tibor- műszaki vezető	31737	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	3	teacher	\N
1043	78	Éliás Máté	31715	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	4	student	\N
1044	78	Hámbor Arnold	31716	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	5	student	\N
1045	78	Balog Tivadar	31744	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	5	teacher	\N
1046	78	Hordós Barnabás	31717	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	6	student	\N
1047	78	Hordós Andrea	31738	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	6	teacher	\N
1048	78	Szalai Zsuzsanna	31740	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	6	teacher	\N
1049	78	Katona Tamás	31718	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	7	student	\N
1050	78	Ádám Sándor	31742	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	7	teacher	\N
1051	78	Király Dániel	31719	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	8	student	\N
1052	78	Kovács Bence	31720	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	9	student	\N
1053	78	Gál Géza	31749	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	9	teacher	\N
1054	78	Babus Mónika	31743	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	9	teacher	\N
1055	78	Kovács Megyer	31721	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	10	student	\N
1056	78	Kaló István	31741	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	11	teacher	\N
1057	78	Nagy Botond Bendegúz	31722	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	11	student	\N
1058	78	Borbélyné Remes Zsuzsa	31745	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	11	teacher	\N
1059	78	Papp Simon	31723	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	12	student	\N
1060	78	Dr.Petrovicsné Sasvári Zsuzsanna	31746	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	12	teacher	\N
1061	78	Polnai Alexander Illés	31724	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	13	student	\N
1062	78	Tomcsik Erika	31754	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	13	teacher	\N
1063	78	Farkas László	31747	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	13	teacher	\N
1064	78	Rafael Péter	31725	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	14	student	\N
1065	78	Simonyák Dávid	31726	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	15	student	\N
1066	78	Várallyay Johanna	31755	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	15	teacher	\N
1067	78	Varga Csaba	31756	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	15	teacher	\N
1068	78	Harkó Erzsébet	31750	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	16	teacher	\N
1069	78	Patai Gábor	31751	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	16	teacher	\N
1070	78	Striteczky Attila	31727	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	16	student	\N
1071	78	Fodor Judit	31748	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	17	teacher	\N
1072	78	Szabó Gergő	31728	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	17	student	\N
1073	78	Szankó Dominik Richárd	31729	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	18	student	\N
1074	78	Simon Veronika	31752	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	18	teacher	\N
1075	78	Sólyomvári Tamás	31753	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	18	teacher	\N
1076	78	Szilágyi Zsombor	31730	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	19	student	\N
1077	78	Zombori Norman	31731	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	20	student	\N
1078	78	Zsarnai Balázs	31732	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	21	student	\N
1079	78	Zsarnai Zalán	31733	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	22	student	\N
1080	85	Balázs Viktória	31757	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	0	student	\N
1081	85	Dr. Szénásy Andrea	31859	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	0	teacher	\N
1082	85	Drajkó Gergő	31864	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	1	teacher	\N
1083	85	Bánfi Bence	31758	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	1	student	\N
1084	85	Beno Santiago	31759	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	2	student	\N
1085	85	Greff Tamás	31856	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	2	teacher	\N
1086	85	Bódi Kristóf	31760	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	3	student	\N
1087	85	Hegyesi Katalin	31861	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	3	teacher	\N
1088	85	Kovács Noémi	31866	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	4	teacher	\N
1089	85	Budai Lili	31761	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	4	student	\N
1090	85	Kővári Dorottya	31857	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	5	teacher	\N
1091	85	Czinege Balázs	31762	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	5	student	\N
1092	85	Orosz Vivien	31858	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	6	teacher	\N
1093	85	Dulkai Csenge Larina	31763	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	6	student	\N
1094	85	Farkas Lorina	31764	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	7	student	\N
1095	85	Otóné István Eszter	31865	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	7	teacher	\N
1096	85	Földi Patrik	31765	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	8	student	\N
1097	85	Selényi Beatrix Vanda	31860	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	8	teacher	\N
1098	85	German Luca	31766	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	9	student	\N
1099	85	Szilfai-Gyóni Ibolya	31863	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	9	teacher	\N
1100	85	Gyarmati Levente	31767	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	10	student	\N
1101	85	Varga Beáta	31862	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	10	teacher	\N
1102	85	Halasi Hanna Imola	31768	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	11	student	\N
1103	85	Fördősné Rozmán Edina	31853	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	11	teacher	\N
1104	85	Hrozina Blanka	31769	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	12	student	\N
1105	85	Gerendai Márk	31854	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	12	teacher	\N
1106	85	Gergely Zsolt	31851	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	13	teacher	\N
1107	85	Jakus Nóra	31770	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	13	student	\N
1108	85	Kovács Kinga	31771	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	14	student	\N
1109	85	Pajorné Menyhárt Mónika	31852	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	14	teacher	\N
1110	85	Kurucz Hanga	31772	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	15	student	\N
1111	85	Végh-Alpár Noémi	31855	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	15	teacher	\N
1112	85	Leskó Laura Anna	31773	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	16	student	\N
1113	85	Lévai Anna	31774	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	17	student	\N
1114	85	Lomen Anna	31775	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	18	student	\N
1115	85	Nádudvari Tamás	31776	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	19	student	\N
1116	85	Palotás Lilien	31777	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	20	student	\N
1117	85	Pápai Dóra Jozefin	31778	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	21	student	\N
1118	85	Papp Sára	31779	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	22	student	\N
1119	85	Pokorny Dániel Zsombor	31780	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	23	student	\N
1120	85	Ragács Róbert Zoltán	31781	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	24	student	\N
1121	85	Rammer Martin	31782	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	25	student	\N
1122	85	Sarankó Liza	31783	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	26	student	\N
1123	85	Sári Anna Virág	31784	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	27	student	\N
1124	85	Temesvári Vivien	31785	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	28	student	\N
1125	85	Thury Kitti	31786	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	29	student	\N
1126	85	Törő Panka	31787	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	30	student	\N
1127	85	Tüske Balázs Gábor	31788	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	31	student	\N
1128	85	Zemeny Gréta	31789	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	32	student	\N
1129	91	Bagyinka Anna	31818	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	0	student	\N
1130	91	Anikó néni	32878	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	0	teacher	\N
1131	91	Bánhegyi Ákos	31823	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	1	student	\N
1132	91	Bencze Dávid	32880	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	1	teacher	\N
1133	91	Csepregi András	32884	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	2	teacher	\N
1134	91	Bitemo Artúr Márk	31816	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	2	student	\N
1135	91	Csernus Rita	32887	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	3	teacher	\N
1136	91	Bóday Dávid	31827	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	3	student	\N
1137	91	Czapekné Egervári Orsolya	32894	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	4	teacher	\N
1138	91	Borzák Bonifác Péter	31830	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	4	student	\N
1139	91	Borzován András	31832	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	5	student	\N
1140	91	Dr. Érfalvy Lívia	32900	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	5	teacher	\N
1141	91	Ecker Anita	32893	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	6	teacher	\N
1142	91	Bujdosó Antal Károly	31829	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	6	student	\N
1143	91	Dicső Bíborka Krisztina	31839	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	7	student	\N
1144	91	Fabiny Márton	32896	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	7	teacher	\N
1145	91	Galgóczy Gábor	32881	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	8	teacher	\N
1146	91	Erdős Gabriella Fruzsina	31835	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	8	student	\N
1147	91	Gianone Kinga	32895	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	9	teacher	\N
1148	91	Erdősi Réka	31836	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	9	student	\N
1149	91	Farszky Máté	31820	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	10	student	\N
1150	91	Hajdó Ákos	32899	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	10	teacher	\N
1151	91	Hajnal-Tóth Ádám	31831	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	11	student	\N
1152	91	Hámor Endre	32898	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	11	teacher	\N
1153	91	Jancsó Gábor Zsolt	31828	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	12	student	\N
1154	91	Hűvös Tamás	32886	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	12	teacher	\N
1155	91	Kis Nataly Zsofi	31838	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	13	student	\N
1156	91	Karakas Mariann	32892	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	13	teacher	\N
1157	91	Komoly Flórián Iván	31825	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	14	student	\N
1158	91	Károlyfalvi Zsolt	32885	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	14	teacher	\N
1159	91	Kovácsné Gyarmathi Krisztina	32891	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	15	teacher	\N
1160	91	Laskovics Milán Máté	31821	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	15	student	\N
1161	91	Nagy Sándor	32882	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	16	teacher	\N
1162	91	Mácsai István	31817	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	16	student	\N
1163	91	Pál Bea	32970	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	17	teacher	\N
1164	91	Málits Luca Nóra	31840	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	17	student	\N
1165	91	Schranz Ambrus	32888	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	18	teacher	\N
1166	91	Malomka Vanessza	31833	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	18	student	\N
1167	91	Mátó Nóra	31843	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	19	student	\N
1168	91	Szűcs Emese	32889	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	19	teacher	\N
1169	91	Mohácsi Hanga	31834	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	20	student	\N
1170	91	Tasnádi Zsuzsanna	32897	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	20	teacher	\N
1171	91	Timi néni	32879	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	21	teacher	\N
1172	91	Narancsik Luca	31841	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	21	student	\N
1173	91	Valló Eszter	32890	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	22	teacher	\N
1174	91	Nász Roland	31824	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	22	student	\N
1175	91	Varga Tamásné	32883	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	23	teacher	\N
1176	91	Stefkó Anna Flóra	31842	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	23	student	\N
1177	91	Szpisák András	31819	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	24	student	\N
1178	91	Valér Miklós Norbert	31826	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	25	student	\N
1179	91	Walkó Abigél	31837	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	26	student	\N
1180	91	Zahorán Szabolcs János	31822	\N	2025-11-30 13:29:11	2025-11-30 13:29:11	27	student	\N
1181	17	Baksza Dávid	31897	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	0	teacher	\N
1182	17	Abos Péter	31899	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	0	student	\N
1183	17	Blaha Péter	31898	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	1	teacher	\N
1184	17	Agócs Gergely Botond	31900	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	1	student	\N
1185	17	Foltán Zoltán	31889	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	2	teacher	\N
1186	17	Balogh Gergő Botond	31901	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	2	student	\N
1187	17	Kemenes Tamás	31883	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	3	teacher	\N
1188	17	Baradlai Patrik Ferenc	31902	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	3	student	\N
1189	17	Bayer Bálint	31903	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	4	student	\N
1190	17	Kovács László	31885	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	4	teacher	\N
1191	17	Orgoványi József	31895	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	5	teacher	\N
1192	17	Béki Márton	31904	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	5	student	\N
1193	17	Somoskői Balázs Donát	31890	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	6	teacher	\N
1194	17	Boros Jonatán	31905	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	6	student	\N
1195	17	Czinege Márton	31906	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	7	student	\N
1196	17	Szabó Attila	31894	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	7	teacher	\N
1197	17	Drajkó Gergő	31907	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	8	student	\N
1198	17	Szász Csilla	31892	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	8	teacher	\N
1199	17	Trieb Márton	31887	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	9	teacher	\N
1200	17	Erdővölgyi Bendegúz	31908	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	9	student	\N
1201	17	Érsek Huba	31909	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	10	student	\N
1202	17	Váradi Ágnes	31888	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	10	teacher	\N
1203	17	Véglesiné Bíró Erzsébet	31886	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	11	teacher	\N
1204	17	Farkas Áron	31910	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	11	student	\N
1205	17	György Zoltán Szilárd	31911	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	12	student	\N
1206	17	Vidra András	31896	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	12	teacher	\N
1207	17	Virág György	31893	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	13	teacher	\N
1208	17	Hajtó Lili	31912	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	13	student	\N
1209	17	Wiezl Csaba	31884	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	14	teacher	\N
1210	17	Hekli Bence	31913	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	14	student	\N
1211	17	Horlik Nimród Imre	31914	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	15	student	\N
1212	17	Dian János	32871	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	15	teacher	\N
1213	17	Horváth Márk	31915	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	16	student	\N
1214	17	Szlobodnikné Kiss Edit	32872	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	16	teacher	\N
1215	17	Fábián Gábor	32870	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	17	teacher	\N
1216	17	Kövesdi Márk	31916	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	17	student	\N
1217	17	Molnár Mária	32873	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	18	teacher	\N
1218	17	Nádasi Gergő	31917	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	18	student	\N
1219	17	Horváth László	32874	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	19	teacher	\N
1220	17	Okolenszki Zalán	31918	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	19	student	\N
1221	17	Pistyúr Zoltán	31891	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	20	teacher	\N
1222	17	Ondrik Barnabás	31919	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	20	student	\N
1223	17	Oszaczki Csaba	31920	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	21	student	\N
1224	17	Ozsváth Bendegúz Máté	31921	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	22	student	\N
1225	17	Radics Ádám	31922	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	23	student	\N
1226	17	Selmeczy György	31923	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	24	student	\N
1227	17	Simák Balázs István	31924	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	25	student	\N
1228	17	Szabó Balázs Sámuel	31925	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	26	student	\N
1229	17	Szalay Máté	31926	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	27	student	\N
1230	17	Szántó Dávid	31927	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	28	student	\N
1231	17	Törő Marcell	31928	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	29	student	\N
1232	17	Veres Milán	31929	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	30	student	\N
1233	36	Schramek Anikó, osztályfőnök, fizika	32028	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	0	teacher	\N
1234	36	Albert Ádám	31993	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	0	student	\N
1235	36	Beke Márton Csaba	31994	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	1	student	\N
1236	36	Számadóné Biró Alice Anikó, angol	32029	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	1	teacher	\N
1237	36	Kálmán Levente	32030	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	2	teacher	\N
1238	36	Bertók-Bálint Ticián	31995	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	2	student	\N
1239	36	Kivovics Judit, angol	32031	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	3	teacher	\N
1240	36	Czerovszki Milán	31996	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	3	student	\N
1241	36	Csernyik Petra	31997	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	4	student	\N
1242	36	Szövényi-Luxné Szabó Teréz	32032	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	4	teacher	\N
1243	36	Szabó Katalin, angol	32033	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	5	teacher	\N
1244	36	Csorba Dániel	31998	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	5	student	\N
1245	36	Laczkó Ágnes, angol	32034	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	6	teacher	\N
1246	36	Erdélyi Dominik	31999	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	6	student	\N
1247	36	Szabó Márta, német	32035	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	7	teacher	\N
1248	36	Horváth Anna	32000	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	7	student	\N
1249	36	Horváth Zalán	32001	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	8	student	\N
1250	36	Pásztiné Markella Eszter, német	32036	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	8	teacher	\N
1251	36	Tar-Pálfi Nikoletta, német	32037	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	9	teacher	\N
1252	36	Huszty Mária Csenge	32002	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	9	student	\N
1253	36	Kocsis Marcell	32003	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	10	student	\N
1254	36	Bakuczné Szabó Gabriella, német	32038	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	10	teacher	\N
1255	36	Koncz Krisztián Bertalan	32004	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	11	student	\N
1256	36	Müllner Hedda, olasz	32039	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	11	teacher	\N
1257	36	Kőhegyi Dávid	32005	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	12	student	\N
1258	36	Becsák Viktória, orosz	32040	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	12	teacher	\N
1259	36	Laduver Péter	32006	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	13	student	\N
1260	36	Keglevich Kristóf, kémia, történelem	32041	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	13	teacher	\N
1261	36	Dr. Nagy Piroska	32042	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	14	teacher	\N
1262	36	Liang Jin Yun	32007	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	14	student	\N
1263	36	Orosz Gyula, matematika	32043	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	15	teacher	\N
1264	36	Luo Han	32008	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	15	student	\N
1265	36	Ádám	32044	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	16	teacher	\N
1266	36	Morvai Gergő	32009	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	16	student	\N
1267	36	Nguyen Gia Kiet	32010	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	17	student	\N
1268	36	Szeibert Janka, matematika	32045	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	17	teacher	\N
1269	36	Foki Tamás, történelem	32046	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	18	teacher	\N
1270	36	Olláry Samu	32011	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	18	student	\N
1271	36	Sásdi Mariann, informatika/digitális kultúra	32047	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	19	teacher	\N
1272	36	Páricsi-Nagy Rezeda	32012	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	19	student	\N
1273	36	Németh Sándor, ének-zene	32048	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	20	teacher	\N
1274	36	Póti Levente	32013	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	20	student	\N
1275	36	Nagy Péter, biológia	32049	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	21	teacher	\N
1276	36	Regula Gergely Péter	32014	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	21	student	\N
1277	36	Sólymosné Hirsch Erika, biológia	32050	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	22	teacher	\N
1278	36	Sipos Bodza Lilla	32015	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	22	student	\N
1279	36	Ujlaki Tibor, irodalom	32051	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	23	teacher	\N
1280	36	Stelcz Anna Réka	32016	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	23	student	\N
1281	36	Garamvölgyi Béla, rajz/vizuális kultúra	32052	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	24	teacher	\N
1282	36	Sümeghi Nándor	32017	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	24	student	\N
1283	36	Tóth Imre, testnevelés	32053	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	25	teacher	\N
1284	36	Szita Péter Levente	32018	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	25	student	\N
1285	36	Téti Miklós	32019	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	26	student	\N
1286	36	Kádárné Szalay Eszter, földrajz	32054	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	26	teacher	\N
1287	36	Takács Márta	32055	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	27	teacher	\N
1288	36	Tiliczki Judit	32020	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	27	student	\N
1289	36	Ujvári Sarolta	32021	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	28	student	\N
1290	36	Váradi János	32022	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	29	student	\N
1291	36	Vig Viktor Benjámin	32023	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	30	student	\N
1292	36	Vu Minh Hoa	32024	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	31	student	\N
1293	36	Wan Zhijie Viktor	32025	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	32	student	\N
1294	36	Zhang Ziteng	32026	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	33	student	\N
1295	36	Zólomy Csanád Zsolt	32027	\N	2025-11-30 13:29:12	2025-11-30 13:29:12	34	student	\N
1296	57	Braun Zsófia	32766	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	0	student	\N
1297	57	Bata Enikő	32801	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	0	teacher	\N
1298	57	Czifra Eszter	32767	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	1	student	\N
1299	57	Duhonyi Anita	32803	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	1	teacher	\N
1300	57	Csóri Zoé Hanna	32768	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	2	student	\N
1301	57	Blahóné Vona Csilla	32802	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	2	teacher	\N
1302	57	Érsek Nóra	32769	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	3	student	\N
1303	57	Molnár Rita	32807	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	3	teacher	\N
1304	57	Fábián Kitti	32770	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	4	student	\N
1305	57	Merész Henrietta	32806	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	4	teacher	\N
1306	57	Franyó Hanna	32771	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	5	student	\N
1307	57	Horváth Edit	32798	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	5	teacher	\N
1308	57	Steidl Levente	32797	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	6	teacher	\N
1309	57	Friesz Anna	32772	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	6	student	\N
1310	57	Gáspár Anna	32773	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	7	student	\N
1311	57	Lakatos-Tombácz Ádám	32799	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	7	teacher	\N
1312	57	Kardos József	32804	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	8	teacher	\N
1313	57	Gólya Levente	32774	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	8	student	\N
1314	57	Gulyás Boróka	32775	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	9	student	\N
1315	57	Szőke László	32810	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	9	teacher	\N
1316	57	Hriagyel Linetta	32776	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	10	student	\N
1317	57	Kenderessy Tibor	32805	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	10	teacher	\N
1318	57	Sajgó Emese	32808	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	11	teacher	\N
1319	57	Jenei Balázs	32777	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	11	student	\N
1320	57	Untsch Gergely Ádám	32811	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	12	teacher	\N
1321	57	Katona Lara Boglárka	32778	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	12	student	\N
1322	57	Kiss Boglárka	32779	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	13	student	\N
1323	57	Strenner Anita Heléna	32809	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	13	teacher	\N
1324	57	Kovácsné Kóka Marianna	32800	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	14	teacher	\N
1325	57	Kiss Veronika	32780	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	14	student	\N
1326	57	Kis-Vén Veronika	32781	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	15	student	\N
1327	57	Máté Ádám	32782	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	16	student	\N
1328	57	Mezei Virág	32783	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	17	student	\N
1329	57	Muckstadt Zénó Sándor	32784	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	18	student	\N
1330	57	Oroszlány Réka	32785	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	19	student	\N
1331	57	Pálinkás Petra	32786	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	20	student	\N
1332	57	Paupa Eszter	32787	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	21	student	\N
1333	57	Richter Laura	32788	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	22	student	\N
1334	57	Rottenhoffer Anna Dóra	32789	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	23	student	\N
1335	57	Sikó Lilla Csillag	32790	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	24	student	\N
1336	57	Szabó Virág	32791	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	25	student	\N
1337	57	Szegner Amina Jázmin	32792	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	26	student	\N
1338	57	Szó Enikő	32793	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	27	student	\N
1339	57	Trudics Lara	32794	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	28	student	\N
1340	57	Vizler Bálint Levente	32795	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	29	student	\N
1341	57	White Vince Péter	32796	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	30	student	\N
1342	74	Artner Noémi	32812	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	0	student	\N
1343	74	Bükkösi Hajnal	32855	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	0	teacher	\N
1344	74	Badó Csenge	32813	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	1	student	\N
1345	74	Csákvári Lili	32848	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	1	teacher	\N
1346	74	Baran Bertalan	32814	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	2	student	\N
1347	74	Darabánt Emese	32866	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	2	teacher	\N
1348	74	Bartucz Boglárka	32815	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	3	student	\N
1349	74	Deák Ferenc	32852	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	3	teacher	\N
1350	74	Bata Orsolya	32816	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	4	student	\N
1351	74	Eletto Gianmaria Domenico	32851	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	4	teacher	\N
1352	74	Földesi Dávid	32856	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	5	teacher	\N
1353	74	Bordás Péter	32817	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	5	student	\N
1354	74	Bővíz Flóra	32818	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	6	student	\N
1355	74	Gyetvai Györgyi	32862	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	6	teacher	\N
1356	74	Delea Márton	32819	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	7	student	\N
1357	74	Lissák Bertalan	32850	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	7	teacher	\N
1358	74	Drei Izabell	32820	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	8	student	\N
1359	74	Szendrei Péter	32860	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	8	teacher	\N
1360	74	Klabacsek Rita	32847	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	9	teacher	\N
1361	74	Eisemann Zita	32821	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	9	student	\N
1362	74	Farkas Emma	32822	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	10	student	\N
1363	74	Kocsis Mariann	32868	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	10	teacher	\N
1364	74	Menyhárt Krisztina	32861	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	11	teacher	\N
1365	74	Ferbert Flóra	32823	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	11	student	\N
1366	74	Müller Ágnes	32859	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	12	teacher	\N
1367	74	Gavalovics Míra	32824	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	12	student	\N
1368	74	Nagy Katalin	32869	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	13	teacher	\N
1369	74	Görömbei Blanka	32825	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	13	student	\N
1370	74	Horváth Bálint	32826	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	14	student	\N
1371	74	Nagy Szilvia	32853	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	14	teacher	\N
1372	74	Howle George Elliot	32827	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	15	student	\N
1373	74	Orbán Angelika	32867	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	15	teacher	\N
1374	74	Kovácsi Sára Kata	32828	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	16	student	\N
1375	74	Somkövi Bernadett	32854	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	16	teacher	\N
1376	74	Tandory Gábor	32865	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	17	teacher	\N
1377	74	Máté-Steff Szíra	32829	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	17	student	\N
1378	74	Teremy Krisztina	32863	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	18	teacher	\N
1379	74	Molnár Sophie	32830	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	18	student	\N
1380	74	Péteri Zsuzsanna	32846	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	19	teacher	\N
1381	74	Nagy Lilla	32831	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	19	student	\N
1382	74	Tomor Judit	32849	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	20	teacher	\N
1383	74	Pedone Giulia	32832	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	20	student	\N
1384	74	Péger Dóra	32833	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	21	student	\N
1385	74	Varga Bettina	32864	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	21	teacher	\N
1386	74	Zákonyi Flóra	32858	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	22	teacher	\N
1387	74	Raffay Kristóf	32834	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	22	student	\N
1388	74	Rigó Emese Csilla	32835	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	23	student	\N
1389	74	Zanna Giuliana	32857	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	23	teacher	\N
1390	74	Rónaháty Boglárka	32836	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	24	student	\N
1391	74	Schmidtka Borbála	32837	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	25	student	\N
1392	74	Stróbli Benjámin	32838	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	26	student	\N
1393	74	Szabó Laura	32839	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	27	student	\N
1394	74	Szabó Marcell Dénes	32840	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	28	student	\N
1395	74	Szalay Tamás	32841	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	29	student	\N
1396	74	Tóbiás Levente	32842	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	30	student	\N
1397	74	Tóth Boglárka Panna	32843	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	31	student	\N
1398	74	Víg Csenge	32844	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	32	student	\N
1399	74	Zabó Lilla	32845	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	33	student	\N
1400	5	Bakos Lilla Boglárka	33297	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	0	teacher	\N
1401	5	András Gergő Hunor	32975	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	0	student	\N
1402	5	Barkó Orsolya	33292	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	1	teacher	\N
1403	5	Bánóczki Viktor	32976	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	1	student	\N
1404	5	Bekéné Kucsera Zsuzsanna	33298	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	2	teacher	\N
1405	5	Baranyai Dorina Réka	32977	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	2	student	\N
1406	5	Bobek Márta	33295	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	3	teacher	\N
1407	5	Baranyai Tímea	32978	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	3	student	\N
1408	5	Dörnyei Szilvia Mária	33312	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	4	teacher	\N
1409	5	Bodnár Nóra	32979	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	4	student	\N
1410	5	Erl Andrea	33300	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	5	teacher	\N
1411	5	Bokodi Szilvia	32980	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	5	student	\N
1412	5	Fazekas József	33293	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	6	teacher	\N
1413	5	Dankó Zoltán Kristóf	32981	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	6	student	\N
1414	5	Gulyás Tamás	33290	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	7	teacher	\N
1415	5	Fekete Kata Hanna	32982	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	7	student	\N
1416	5	Foki Regina Zoé	32983	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	8	student	\N
1417	5	Horváth Anikó	33301	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	8	teacher	\N
1418	5	Jakab Zsuzsanna	33296	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	9	teacher	\N
1419	5	Havas Tamás	32984	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	9	student	\N
1420	5	Heringer Péter	32985	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	10	student	\N
1421	5	Józan Péter	33302	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	10	teacher	\N
1422	5	Kántor Péter	33288	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	11	teacher	\N
1423	5	Horváth Patrik	32986	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	11	student	\N
1424	5	Katonáné Tímár Mária	33303	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	12	teacher	\N
1425	5	Józsi Petra Boglárka	32987	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	12	student	\N
1426	5	Lábszki József	33294	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	13	teacher	\N
1427	5	Kéri-Zsigmond Kata	32988	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	13	student	\N
1428	5	Lábszkiné Tatai Ilona	33304	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	14	teacher	\N
1429	5	Kis-Prumik Csenge Boglárka	32989	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	14	student	\N
1430	5	Magyari Gábor	33289	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	15	teacher	\N
1431	5	Kiss Renáta Ramóna	32990	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	15	student	\N
1432	5	Koósz Olívia Henrietta	32991	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	16	student	\N
1433	5	Magyariné Sárdinecz Zsuzsanna	33305	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	16	teacher	\N
1434	5	Kozicz Júlia Anna	32992	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	17	student	\N
1435	5	Mózesné Vincze Jolán	33306	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	17	teacher	\N
1436	5	Polyóka Tamás	33287	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	18	teacher	\N
1437	5	László Réka	32993	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	18	student	\N
1438	5	Lendvai Kamilla	32994	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	19	student	\N
1439	5	Szalai Gizella	33307	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	19	teacher	\N
1440	5	Szalay Izabella	33291	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	20	teacher	\N
1441	5	Makay Kamilla	32995	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	20	student	\N
1442	5	Molnár Ádám János	32996	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	21	student	\N
1443	5	Szeimann Zsuzsanna	33308	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	21	teacher	\N
1444	5	Szokoli Kinga	33299	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	22	teacher	\N
1445	5	Nagy Mátyás	32997	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	22	student	\N
1446	5	Tara Andrea	33309	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	23	teacher	\N
1447	5	Németh Lili	32998	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	23	student	\N
1448	5	Novák Dorka	32999	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	24	student	\N
1449	5	Vadné Vankó Alíz	33310	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	24	teacher	\N
1450	5	Pruzsina Patrícia	33000	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	25	student	\N
1451	5	Wéber Balázs	33311	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	25	teacher	\N
1452	5	Sántha Hanga Menta	33001	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	26	student	\N
1453	5	Simon Boglárka Anna	33002	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	27	student	\N
1454	5	Spóner Jázmin	33003	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	28	student	\N
1455	5	Szépfi Hanna Flóra	33004	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	29	student	\N
1456	5	Szommer Alíz	33005	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	30	student	\N
1457	5	Török Judith Dalma	33006	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	31	student	\N
1458	5	Varga Roberta	33007	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	32	student	\N
1459	5	Wachter Eliza Anita	33008	\N	2025-11-30 13:29:13	2025-11-30 13:29:13	33	student	\N
1460	6	Balaton Levente	33038	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	0	student	\N
1461	6	Arnóczkyné Szabó Nóra	33072	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	0	teacher	\N
1462	6	Ban Ákos	33039	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	1	student	\N
1463	6	Bakos Lilla Boglárka	33085	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	1	teacher	\N
1464	6	Baráth Zsombor	33040	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	2	student	\N
1465	6	Bekéné Kucsera Zsuzsanna	33086	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	2	teacher	\N
1466	6	Bernvallner Gergő	33041	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	3	student	\N
1467	6	Bobek Márta	33083	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	3	teacher	\N
1468	6	Borbáth István	33042	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	4	student	\N
1469	6	Dörnyei Szilvia Mária	33084	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	4	teacher	\N
1470	6	Brunner Liza	33043	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	5	student	\N
1471	6	Geiszt Ferenc Dezső	33073	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	5	teacher	\N
1472	6	Boda Helga	33044	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	6	student	\N
1473	6	Gödri Krisztina	33074	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	6	teacher	\N
1474	6	Erős Barnabás	33045	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	7	student	\N
1475	6	Polyóka Tamás	33069	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	7	teacher	\N
1476	6	Gaál Balázs	33046	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	8	student	\N
1477	6	Harmath Zoltánné	33078	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	8	teacher	\N
1478	6	Grásel Péter	33047	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	9	student	\N
1479	6	Horváth Anikó	33087	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	9	teacher	\N
1480	6	Körtvélyfáy Attila	33088	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	10	teacher	\N
1481	6	Groszkopf Titanilla	33048	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	10	student	\N
1482	6	Herpai Péter	33049	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	11	student	\N
1483	6	Lábszkiné Tatai Ilona	33082	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	11	teacher	\N
1484	6	Horváth Levente	33050	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	12	student	\N
1485	6	Magyariné Sárdinecz Zsuzsanna	33075	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	12	teacher	\N
1486	6	Indi Olivér	33051	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	13	student	\N
1487	6	Németh Ildikó	33076	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	13	teacher	\N
1488	6	Juhász Ambrus	33052	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	14	student	\N
1489	6	Pfiszterer Zsuzsanna	33081	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	14	teacher	\N
1490	6	Szalai Gizella	33080	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	15	teacher	\N
1491	6	Körtvélyfáy János	33053	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	15	student	\N
1492	6	Muszka Zoltán	33071	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	16	teacher	\N
1493	6	Kövesi Viktor	33054	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	16	student	\N
1494	6	Jakab Zsuzsanna	33070	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	17	teacher	\N
1495	6	Lányi Lili	33055	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	17	student	\N
1496	6	Majoros Ádám	33056	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	18	student	\N
1497	6	Szokoli Kinga	33077	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	18	teacher	\N
1498	6	Zámbóné Borvendég Katalin	33079	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	19	teacher	\N
1499	6	Meszner Klaudia	33057	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	19	student	\N
1500	6	Moravcsik Nóra	33058	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	20	student	\N
1501	6	Nagy Barnabás	33059	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	21	student	\N
1502	6	Papp Lóránt	33060	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	22	student	\N
1503	6	Solymár Dávid	33061	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	23	student	\N
1504	6	Szalczinger Anna	33062	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	24	student	\N
1505	6	Tátrai Alíz	33063	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	25	student	\N
1506	6	Tirhold Inez	33064	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	26	student	\N
1507	6	Tóth Klaudia	33065	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	27	student	\N
1508	6	Varga Kincső	33066	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	28	student	\N
1509	6	Végh Vanessza	33067	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	29	student	\N
1510	6	Vida Janka	33068	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	30	student	\N
1511	16	Baksza Dávid	33147	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	0	teacher	\N
1512	16	Barnák Dominik	33089	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	0	student	\N
1513	16	Csapó Ádám András	33090	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	1	student	\N
1514	16	Demeter Gergő	33091	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	2	student	\N
1515	16	Dóka Bálint	33092	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	3	student	\N
1516	16	Kemenes Tamás	33153	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	3	teacher	\N
1517	16	Czene Gábor	33124	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	4	teacher	\N
1518	16	Eiler Ákos	33093	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	4	student	\N
1519	16	Farkas Verona Mária	33094	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	5	student	\N
1520	16	Palkó-Nagy Márta	33125	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	5	teacher	\N
1521	16	Gyuricza Botond Balázs	33095	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	6	student	\N
1522	16	Lustyik Ágnes	33126	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	6	teacher	\N
1523	16	Mocsári Nóra	33127	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	7	teacher	\N
1524	16	Szabó Attila	33146	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	7	teacher	\N
1525	16	Hangodi István Ábris	33096	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	7	student	\N
1526	16	Herczig Balázs	33097	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	8	student	\N
1527	16	Schofferné Szász Ildikó	33128	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	8	teacher	\N
1528	16	Hoó-Lantos Lilla	33098	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	9	student	\N
1529	16	Hevér János	33129	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	9	teacher	\N
1530	16	Horváth Nándor	33099	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	10	student	\N
1531	16	Nagy Péter	33130	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	10	teacher	\N
1532	16	Joó Bálint	33100	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	11	student	\N
1533	16	Bíró-Sturcz Anita	33131	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	11	teacher	\N
1534	16	Kocsis Tamás	33101	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	12	student	\N
1535	16	Nemes Tibor	33145	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	12	teacher	\N
1536	16	Faroun-Cserekly Éva	33132	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	12	teacher	\N
1537	16	Korbely Bence	33102	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	13	student	\N
1538	16	Rauscher István	33133	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	13	teacher	\N
1539	16	Wiezl Csaba	33154	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	14	teacher	\N
1540	16	Gyetván Károly	33134	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	14	teacher	\N
1541	16	Kucsera Gergő	33103	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	14	student	\N
1542	16	Kürti Kende	33104	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	15	student	\N
1543	16	Dian János	33121	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	15	teacher	\N
1544	16	Szlobodnikné Kiss Edit	33122	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	16	teacher	\N
1545	16	Fabók Botond	33136	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	16	teacher	\N
1546	16	Lajcsok Hanga	33105	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	16	student	\N
1547	16	Fábián Gábor	33120	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	17	teacher	\N
1548	16	Lehel Dániel	33106	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	17	student	\N
1549	16	Tóth Barnabás	33141	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	17	teacher	\N
1550	16	Burger Balázs Péter	33137	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	17	teacher	\N
1551	16	Molnár Levente	33107	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	18	student	\N
1552	16	Molnár Mária	33155	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	18	teacher	\N
1553	16	Bea Mónika Izabella	33138	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	18	teacher	\N
1554	16	Novák Mónika Zsuzsanna	33139	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	19	teacher	\N
1555	16	Molnár-Arany Naomi	33108	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	19	student	\N
1556	16	Wachtler Viktor	33149	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	19	teacher	\N
1557	16	Horváth László	33123	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	19	teacher	\N
1558	16	Pistyúr Zoltán	33135	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	20	teacher	\N
1559	16	Holman Nóra	33140	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	20	teacher	\N
1560	16	Nagy Titusz	33109	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	20	student	\N
1561	16	Rácz Gergő	33110	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	21	student	\N
1562	16	Soós Johanna	33111	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	22	student	\N
1563	16	Bermann Gábor	33142	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	22	teacher	\N
1564	16	Surányi Szilárd Koppány	33112	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	23	student	\N
1565	16	Megyeri Balázs	33143	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	23	teacher	\N
1566	16	Szabó Krisztián Dezső	33113	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	24	student	\N
1567	16	Niedermüllerné Karcag Ildikó	33144	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	24	teacher	\N
1568	16	Szeghalmi Csenge Lívia	33114	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	25	student	\N
1569	16	Szilner Botond	33115	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	26	student	\N
1570	16	Szűcs Enikő Sára	33116	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	27	student	\N
1571	16	Ungár Máté Vazul	33117	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	28	student	\N
1572	16	Szabó Zsombor János	33148	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	28	teacher	\N
1573	16	Ungár Sára Melinda	33118	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	29	student	\N
1574	16	Urbán Andrea Judit	33150	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	30	teacher	\N
1575	16	Vigh Bence	33119	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	30	student	\N
1576	16	Daróczi Éva	33151	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	31	teacher	\N
1577	16	Steiner Krisztina	33152	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	32	teacher	\N
1578	50	Papp Rebeka	33344	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	0	teacher	\N
1579	50	Csóka Hanna Boglárka	33157	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	1	student	\N
1580	50	Gönczi Sándor	33346	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	1	teacher	\N
1581	50	Csoma Linda Szófia	33158	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	2	student	\N
1582	50	Lakos Erika	33347	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	2	teacher	\N
1583	50	Braxátor Marianna	33352	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	2	teacher	\N
1584	50	Domján Alexa	33159	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	3	student	\N
1585	50	Gili Nóra Márta	33160	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	4	student	\N
1586	50	Juhászné Makovics Erzsébet	33343	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	4	teacher	\N
1587	50	Gilicze Dorka	33161	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	5	student	\N
1588	50	Günther Győző	33342	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	5	teacher	\N
1589	50	Gyarmati Emma	33162	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	6	student	\N
1590	50	dr. Hicz János	33341	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	6	teacher	\N
1591	50	Fülöpné Kakas Zsuzsa	33353	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	6	teacher	\N
1592	50	Gyarmati Lilla	33163	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	7	student	\N
1593	50	Kuli Ferenc	33340	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	7	teacher	\N
1594	50	Halla Bianka	33164	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	8	student	\N
1595	50	Tamás Tünde	33348	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	8	teacher	\N
1596	50	Hartai Vencel György	33165	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	9	student	\N
1597	50	Kassainé Málnási Ágnes	33345	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	9	teacher	\N
1598	50	Hellebrandt Noémi	33166	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	10	student	\N
1599	50	Horgász Boldizsár	33167	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	11	student	\N
1600	50	Kolláth Károly Ferenc	33360	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	12	teacher	\N
1601	50	Kardos Kinga	33168	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	12	student	\N
1602	50	Domokos Berta	33357	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	12	teacher	\N
1603	50	Jakus-Halász Katalin	33349	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	12	teacher	\N
1604	50	Kozelkin Dária	33169	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	13	student	\N
1605	50	Kovács Kálmán	33339	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	13	teacher	\N
1606	50	Kőfaragó Mátyás	33170	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	14	student	\N
1607	50	Kövér Zsófia	33171	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	15	student	\N
1608	50	Ujvárosi Emese	33351	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	15	teacher	\N
1609	50	Simon László	33350	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	15	teacher	\N
1610	50	Králik Márton Pál	33172	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	16	student	\N
1611	50	Horváth Gergely Bálint	33355	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	16	teacher	\N
1612	50	Lőrinczi Linda	33173	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	17	student	\N
1613	50	Gegő Kinga	33356	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	17	teacher	\N
1614	50	Mészáros Márk Máté	33174	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	18	student	\N
1615	50	Csenki Katalin	33358	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	18	teacher	\N
1616	50	Marosvölgyi Veronika	33354	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	18	teacher	\N
1617	50	Mezei Balázs Dávid	33175	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	19	student	\N
1618	50	Bíró Gergely Levente	33359	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	20	teacher	\N
1619	50	Nagy-Bíró Blanka	33176	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	20	student	\N
1620	50	Nagy-Bíró Eliza	33177	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	21	student	\N
1621	50	Oláh Bianka	33178	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	22	student	\N
1622	50	Oláh Regina	33179	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	23	student	\N
1623	50	Patócs Ákos	33180	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	24	student	\N
1624	50	Petrák Ádám	33181	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	25	student	\N
1625	50	Taar Ádám László	33182	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	26	student	\N
1626	50	Tassy Roland	33183	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	27	student	\N
1627	50	Tóth Vivien	33184	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	28	student	\N
1628	50	Vadász Dávid	33185	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	29	student	\N
1629	50	Vályi-Nagy Mira	33186	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	30	student	\N
1630	50	Varga Alíz	33187	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	31	student	\N
1631	50	Vilmányi Andrea Hanna	33188	\N	2025-11-30 13:29:14	2025-11-30 13:29:14	32	student	\N
1632	70	Békési Blanka Dóra	33209	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	0	student	\N
1633	70	Berki Dóra	33210	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	1	student	\N
1634	70	Szilágyiné Manasses Melinda	33336	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	1	teacher	\N
1635	70	Ábrahám Hedvig	33313	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	2	teacher	\N
1636	70	Bihari Zsófia	33211	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	2	student	\N
1637	70	Böőr Bíborka	33212	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	3	student	\N
1638	70	Almásiné Nemeshegyi Gyopárka	33314	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	3	teacher	\N
1639	70	Butenkov Emma	33213	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	4	student	\N
1640	70	Burjánné Török Orsolya	33315	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	4	teacher	\N
1641	70	Csordás Lászlóné	33316	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	5	teacher	\N
1642	70	Dienes Panna	33214	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	5	student	\N
1643	70	Erdélyi Zalán	33215	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	6	student	\N
1644	70	Dányi-Szabó Katalin	33317	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	6	teacher	\N
1645	70	Farkas Éva	33318	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	7	teacher	\N
1646	70	Farkas Virág	33216	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	7	student	\N
1647	70	Gál Zoltán	33319	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	8	teacher	\N
1648	70	Fekete Kinga	33217	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	8	student	\N
1649	70	Felker Hanna	33218	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	9	student	\N
1650	70	Hársfalvi Anikó	33320	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	9	teacher	\N
1651	70	Majer Tamás	33337	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	10	teacher	\N
1652	70	Ferencz Kristóf	33219	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	10	student	\N
1653	70	Tarjánné Sólyom Ildikó	33338	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	11	teacher	\N
1654	70	Füredi-Trummer András	33220	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	11	student	\N
1655	70	Hernyákné Molnár Tünde	33321	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	12	teacher	\N
1656	70	Hollósy Dániel Balázs	33221	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	12	student	\N
1657	70	Illés Zoltánné Ujvári Éva	33322	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	13	teacher	\N
1658	70	Horváth Hanna Edina	33222	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	13	student	\N
1659	70	Ipacs Dóra	33223	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	14	student	\N
1660	70	Kömley Pálma	33324	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	14	teacher	\N
1661	70	Juhász Róbert	33224	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	15	student	\N
1662	70	Krix Antalné	33323	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	15	teacher	\N
1663	70	Kassa Zoé	33225	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	16	student	\N
1664	70	Lutter András	33326	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	16	teacher	\N
1665	70	Paróczay Eszter	33327	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	17	teacher	\N
1666	70	Kelenczés Orsolya	33226	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	17	student	\N
1667	70	Podányi Viktória	33329	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	18	teacher	\N
1668	70	Lukács Dominik	33227	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	18	student	\N
1669	70	Simánné Horváth Zsuzsanna	33330	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	19	teacher	\N
1670	70	Markó Marcell	33228	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	19	student	\N
1671	70	Szegedi-Viczencz Katalin	33331	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	20	teacher	\N
1672	70	Miron Kata	33229	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	20	student	\N
1673	70	Tuzson-Berczeli Tamás	33334	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	21	teacher	\N
1674	70	Németh Rebeka	33230	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	21	student	\N
1675	70	Péczeli Ádám	33328	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	22	teacher	\N
1676	70	Orbán Réka	33231	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	22	student	\N
1677	70	Lengyel-Precskó Lilian	33325	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	23	teacher	\N
1678	70	Pölczman Dávid	33232	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	23	student	\N
1679	70	Szűcsné Stadler Lilla	33332	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	24	teacher	\N
1680	70	Reznek Laura	33233	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	24	student	\N
1681	70	Takács Erika	33333	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	25	teacher	\N
1682	70	Sárosi Katica	33234	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	25	student	\N
1683	70	Sebestyén Zente	33235	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	26	student	\N
1684	70	Solymosi Luca	33236	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	27	student	\N
1685	70	Suominen Lelle Hilla	33237	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	28	student	\N
1686	70	Szabó Csongor	33238	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	29	student	\N
1687	70	Szabó Vince Csanád	33239	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	30	student	\N
1688	70	Szalai Petra	33240	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	31	student	\N
1689	70	Szanyó András	33241	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	32	student	\N
1690	70	Tomes Milán Dávid	33242	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	33	student	\N
1691	70	Tóth Gergely	33243	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	34	student	\N
1692	70	Virág Luca	33244	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	35	student	\N
1693	70	Sárközi Mihály	33245	\N	2025-11-30 13:29:15	2025-11-30 13:29:15	36	student	\N
\.


--
-- Data for Name: tablo_notes; Type: TABLE DATA; Schema: public; Owner: photo_stack
--

COPY public.tablo_notes (id, tablo_project_id, content, user_id, created_at, updated_at) FROM stdin;
\.


--
-- Name: tablo_api_keys_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photo_stack
--

SELECT pg_catalog.setval('public.tablo_api_keys_id_seq', 1, true);


--
-- Name: tablo_contacts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photo_stack
--

SELECT pg_catalog.setval('public.tablo_contacts_id_seq', 97, true);


--
-- Name: tablo_missing_persons_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photo_stack
--

SELECT pg_catalog.setval('public.tablo_missing_persons_id_seq', 1725, true);


--
-- Name: tablo_notes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photo_stack
--

SELECT pg_catalog.setval('public.tablo_notes_id_seq', 1, false);


--
-- Name: tablo_partners_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photo_stack
--

SELECT pg_catalog.setval('public.tablo_partners_id_seq', 5, true);


--
-- Name: tablo_projects_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photo_stack
--

SELECT pg_catalog.setval('public.tablo_projects_id_seq', 130, true);


--
-- Name: tablo_schools_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photo_stack
--

SELECT pg_catalog.setval('public.tablo_schools_id_seq', 156, true);


--
-- PostgreSQL database dump complete
--

\unrestrict uDSp9r2TtSROOEbfsBBmgOEO7AEaI5BFacAbUfmPeGkgg8zmjsr7s6vjBwkMIMI

