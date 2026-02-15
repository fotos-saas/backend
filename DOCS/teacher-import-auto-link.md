# Tanár Import + AI Auto-Linking

## Parancsok

### 1. Migráció

```bash
php artisan migrate
```

Ez hozzáadja az `external_id` mezőt a `teacher_archive` táblához (unique index: `partner_id + external_id`).

### 2. Import (`teachers:import`)

JSON fájlból importálja a tanárokat a `teacher_archive` táblába.

```bash
# Fájlból
php artisan teachers:import /tmp/partner-3-teachers.json --partner=3

# API URL-ről
php artisan teachers:import --partner=3 --api-url=https://example.com/teachers.json
```

**Mit csinál:**
- Beolvassa a JSON-t (támogatott formátumok: `{"teachers": [...]}` vagy sima tömb)
- Deduplikálja: `(name, school_id)` páronként egy rekord (több projektből jövő azonos tanár = 1 archive rekord)
- `external_id` alapján skip-eli a már importált rekordokat
- Kiolvassa a titulust (Dr./PhD/Prof.) és a pozíciót

**JSON rekord formátum:**
```json
{
  "id": 12345,
  "name": "Dr. Kovács Anna",
  "position": "matematika",
  "tablo_project_id": 67,
  "tablo_school_id": 89,
  "selected_image_url": "https://..."
}
```

**Inkrementális:** Ugyanaz a JSON újra futtatva → 0 új rekord (external_id check).

### 3. Auto-link (`teachers:auto-link`)

Összekapcsolja a cross-school tanárokat (ugyanaz a személy különböző iskolákban).

```bash
# Csak determinisztikus (gyors, nincs API cost)
echo "yes" | php artisan teachers:auto-link --partner=3 --skip-ai

# AI fázissal együtt (~$0.50-1.00 Sonnet cost)
echo "yes" | php artisan teachers:auto-link --partner=3

# Csak az újonnan importált (linked_group IS NULL) tanárokra
echo "yes" | php artisan teachers:auto-link --partner=3 --only-new --skip-ai

# Dry-run (nem ír DB-be, csak statisztikát mutat)
php artisan teachers:auto-link --partner=3 --dry-run
```

**3 fázis:**

| Fázis | Leírás | Cost |
|-------|--------|------|
| A - Determinisztikus | Exact match, prefix match, "-né" variánsok | $0 |
| B - AI (Claude Sonnet) | Iskolánkénti batch, név hasonlóság, pozíció egyezés | ~$0.50-1.00 |
| C - Végrehajtás | `linked_group` UUID update + changelog | $0 |

**Determinisztikus szabályok:**
- Exact normalized match: `Dr. Kovács Anna` = `Kovács Anna` (titulus strip, ékezet normalizálás)
- Prefix match: `Ábrahám Hedvig` ⊂ `Ábrahám Hedvig Marika`
- Házassági név: `Kovácsné` ↔ `Kovács`

**Linked schools:** A `partner_schools.linked_group` alapján az összekapcsolt iskolák tanárai is linkelődnek (pl. "Batthyány Kázmér Gimnázium" school_id=61 és "Szigetszentmiklósi Batthyányi Kázmér" school_id=89).

**Safety:** Ha egy iskolában (raw school_id) 2+ azonos nevű tanár van → nem linkeli automatikusan, AI-ra hagyja.

---

## Szerver deployment

```bash
ssh root@89.167.19.19

# Container keresés (sk8g* prefix, NE fotos-*)
docker ps | grep sk8g

# JSON feltöltés
docker cp /path/to/partner-3-teachers.json <container>:/tmp/partner-3-teachers.json

# 1. Migráció
docker exec <container> php artisan migrate

# 2. Import
docker exec <container> php artisan teachers:import /tmp/partner-3-teachers.json --partner=3

# 3. Auto-link (determinisztikus)
echo "yes" | docker exec -i <container> php artisan teachers:auto-link --partner=3 --skip-ai

# 4. Auto-link más partnerekre
echo "yes" | docker exec -i <container> php artisan teachers:auto-link --partner=24 --skip-ai

# 5. Ellenőrzés
docker exec <container> php artisan tinker --execute="
echo 'Partner 3: ' . App\Models\TeacherArchive::where('partner_id',3)->whereNotNull('linked_group')->count() . ' linked';
echo PHP_EOL . 'Partner 24: ' . App\Models\TeacherArchive::where('partner_id',24)->whereNotNull('linked_group')->count() . ' linked';
"
```

---

## Érintett fájlok

| Fájl | Típus |
|------|-------|
| `app/Console/Commands/ImportTeachersCommand.php` | Artisan command |
| `app/Console/Commands/AutoLinkTeachersCommand.php` | Artisan command |
| `app/Services/Teacher/TeacherAutoLinkService.php` | Üzleti logika |
| `app/Models/TeacherArchive.php` | Model (external_id fillable) |
| `database/migrations/2026_02_15_100000_add_external_id_to_teacher_archive_table.php` | Migráció |
