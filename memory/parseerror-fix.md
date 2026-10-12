---
name: parseerror-fix
description: Fixed ParseError in member-application.blade.php caused by stray </x-guest-layout> closing tag
metadata:
  type: project
---

## Fix Applied

**Root cause**: The source file `resources/views/auth/member-application.blade.php` had a closing `</x-guest-layout>` tag on the last line (line 366), but there was no corresponding opening `<x-guest-layout>` tag anywhere in the file. This caused the Blade compiler to generate a stray `<?php echo $__env->renderComponent(); ?>` followed by `<?php endif; ?>` in the compiled view at `storage/framework/views/7a8030bf48ebeb56fc9e0f8f5f014ba1.php`, resulting in the ParseError "syntax error, unexpected token 'endif'".

**Fix**: Removed the `</x-guest-layout>` closing tag from the end of the blade file. The file was already a self-contained view with its own `@php` block at the top and full form HTML - the `x-guest-layout` wrapper component was unnecessary and was causing the compilation issue.

**Files modified**:
- `resources/views/auth/member-application.blade.php` - removed `</x-guest-layout>` from end of file

**Verification**:
- `php artisan view:clear` - cleared compiled views
- `php artisan tinker` view compilation - confirms no ParseError
- Compiled view no longer has stray `<?php endif; ?>` after `renderComponent()`
- Page serves at http://127.0.0.1:8000/member-application with HTTP 200