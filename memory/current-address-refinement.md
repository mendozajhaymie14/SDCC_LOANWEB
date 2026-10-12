---
name: current-address-refinement
description: Refined Current Address section with restricted dropdown options and fixed barangay cascading
metadata:
  type: project
---

## Refinement Applied

**Scope**: Enhanced the "Current Address" section in membership application form with stricter controls and fixed JavaScript functionality.

### Changes Made

**1. Dropdown Restrictions**
- **Province**: Single fixed option - "National Capital Region (NCR)" (pre-selected)
- **City/Municipality**: Strictly limited to:
  - "Las Piñas City"
  - "Parañaque City"
- **Barangay**: Dynamic cascading with exact barangay lists

**2. Barangay Cascading Fix**
- **JavaScript Update**: Fixed `cityToBarangays` mapping with precise data:
  - Las Piñas City: 17 barangays (including B.F. Intl Village, CAA/B.F. Intl, Almanza Uno/Dos, Talon 1-5, Pamplona 1-3, Pulang Lupa 1-2, Zapote, Manuyo 1-2, Elias Aldana, Ilaya, Daniel Fajardo)
  - Parañaque City: 16 barangays (including Baclaran, Don Galo, La Huerta, San Dionisio, San Isidro, Sto. Niño, Tambo, Vitalez, B.F. Homes, Don Bosco, Marcelo Green, Merville, Moonwalk, San Antonio, San Martin de Porres, Sun Valley)

**3. Field Simplification**
- Removed: Separate "House / Lot No." input field
- Retained: 
  - "Address line" input with helper text: "Unit No./Floor, Building Name, Building/House No., Street, Subdivision/Village"
  - "Zip Code" input

**4. Controller Updates**
- Removed `house_no` validation (field deleted from form)
- Eliminated duplicate permanent address validation block
- Removed `country_of_birth`/`city_of_birth` normalization (fields removed from form)
- Updated address normalization: `['street', 'barangay', 'municipality', 'zip_code']`
- Preserved: Safe upsert logic with `MemberApplication::withTrashed()->where('email', $email)->first()`

**5. Verification**
- `php artisan view:clear` + `config:clear` — cache cleared
- `php artisan tinker --execute="view('auth.member-application')->render();"` — renders without syntax errors
- Confirmed zero references to `house_no`, `country_of_birth`, `city_of_birth` in form fields

### Files Modified
- `resources/views/auth/member-application.blade.php` — UI refinement, dropdown options, JavaScript cascading fix
- `app/Http/Controllers/MemberApplicationController.php` — validation and normalization cleanup

### Outcome
The Current Address section now strictly adheres to the specified UI/UX requirements with functional Province → City → Barangay cascading, reduced field complexity, and safe data handling.