<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\ResourcePermission;
use App\Providers\MoonShineServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Hidden;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

#[Icon('shield-check')]
class RolePermissionsPage extends Page
{
    public function getTitle(): string
    {
        return 'Hak Akses Role';
    }

    public function getBreadcrumbs(): array
    {
        return ['#' => $this->getTitle()];
    }

    protected function isAuthorized(): bool
    {
        return auth(moonshineConfig()->getGuard())->user()?->isSuperUser() ?? false;
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        abort_unless($this->isAuthorized(), 403, 'Anda tidak memiliki akses ke halaman ini.');

        $resourcePermissions = ResourcePermission::orderBy('label')->get();

        $roles = MoonshineUserRole::query()
            ->where('id', '!=', MoonshineUserRole::DEFAULT_ROLE_ID)
            ->orderBy('name')
            ->get();

        return [
            Tabs::make([
                Tab::make('Kelola Resource', $this->manageResourcesComponents($resourcePermissions))
                    ->icon('cog-6-tooth'),

                Tab::make('Atur Akses per Role', $this->roleAccessComponents($roles, $resourcePermissions))
                    ->icon('shield-check'),
            ]),
        ];
    }

    /**
     * @return list<ComponentContract>
     */
    private function manageResourcesComponents(Collection $resourcePermissions): array
    {
        $components = [];

        if ($flash = session('resource_manage_alert')) {
            $components[] = Alert::make(type: $flash['type'])->content($flash['message']);
        }

        $components[] = Alert::make(type: 'info')
            ->content(
                'Resource di bawah bersifat dinamis (tersimpan di database). Resource yang belum ditambahkan di sini otomatis '
                . 'tertutup (hanya Admin yang bisa akses) sampai ditambahkan dan diberi akses ke role tertentu di tab "Atur Akses per Role". '
                . 'Menghapus resource dari daftar ini akan membuatnya kembali tertutup untuk semua role selain Admin.'
            );

        $candidates = $this->availableResourceCandidates($resourcePermissions);

        if ($candidates === []) {
            $components[] = Alert::make(type: 'info')
                ->content('Semua resource yang tersedia (selain resource sistem admin-only) sudah ditambahkan.');
        }

        foreach ($resourcePermissions as $rp) {
            $components[] = FormBuilder::make()
                ->asyncMethod('removeResource')
                ->name('remove-resource-form-' . $rp->id)
                ->fields([
                    Hidden::make('Resource Id', 'resource_id')->default((string) $rp->id),
                    FlexibleRender::make(
                        '<div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;'
                        . 'padding:.75rem 1rem;margin-bottom:.5rem;border:1px solid rgba(128,128,128,.3);'
                        . 'border-radius:.5rem;background:rgba(128,128,128,.05);">'
                        . '<div><strong>' . e($rp->label) . '</strong><br>'
                        . '<span style="font-size:.75rem;opacity:.6;">' . e($rp->resource_class) . '</span></div>'
                        . '<button type="submit" class="btn btn-error btn-sm" style="flex-shrink:0;">Hapus</button>'
                        . '</div>'
                    ),
                ])
                ->hideSubmit();
        }

        if ($candidates !== []) {
            $components[] = FormBuilder::make()
                ->asyncMethod('addResource')
                ->name('add-resource-form')
                ->fields([
                    Select::make('Resource', 'resource_class')
                        ->options($candidates)
                        ->required(),
                    Text::make('Label', 'label')
                        ->required()
                        ->placeholder('Nama yang tampil di matrix akses'),
                ])
                ->submit('Tambah Resource');
        }

        return $components;
    }

    /**
     * @param Collection<int, MoonshineUserRole> $roles
     * @param Collection<int, ResourcePermission> $resourcePermissions
     * @return list<ComponentContract>
     */
    private function roleAccessComponents(Collection $roles, Collection $resourcePermissions): array
    {
        $components = [
            Div::make([])->class('role-permissions-result')->customAttributes(['style' => 'margin-bottom: 1rem;']),

            Alert::make(type: 'info')
                ->content(
                    'Role <strong>Admin</strong> selalu memiliki akses penuh ke semua resource dan tidak dapat diatur di sini. '
                    . 'Untuk role lain, akses <strong>tertutup secara default</strong> — centang resource yang boleh diakses tiap role di bawah. '
                    . 'Gunakan checkbox "Semua" di awal baris untuk memberi satu role akses ke semua resource sekaligus, '
                    . 'atau checkbox "semua" di header kolom untuk memberi satu resource ke semua role sekaligus.'
                ),
        ];

        if ($roles->isEmpty()) {
            $components[] = Alert::make(type: 'warning')
                ->content('Belum ada role selain Admin. Buat role baru terlebih dahulu di menu Roles.');
        } elseif ($resourcePermissions->isEmpty()) {
            $components[] = Alert::make(type: 'warning')
                ->content('Belum ada resource yang dikelola. Tambahkan resource terlebih dahulu di tab "Kelola Resource".');
        } else {
            $components[] = FormBuilder::make()
                ->asyncMethod('save')
                ->name('role-permissions-form')
                ->fields([
                    FlexibleRender::make($this->renderMatrix($roles, $resourcePermissions)),
                ])
                ->hideSubmit();
        }

        return $components;
    }

    /**
     * @param Collection<int, ResourcePermission> $resourcePermissions
     * @return array<string, string>
     */
    private function availableResourceCandidates(Collection $resourcePermissions): array
    {
        $tracked = $resourcePermissions->pluck('resource_class')->all();
        $candidates = [];

        foreach (moonshine()->getResources() as $resource) {
            $class = get_class($resource);

            if (in_array($class, MoonShineServiceProvider::ADMIN_ONLY_RESOURCES, true)) {
                continue;
            }

            if (in_array($class, $tracked, true)) {
                continue;
            }

            $candidates[$class] = $resource->getTitle();
        }

        return $candidates;
    }

    /**
     * @param Collection<int, MoonshineUserRole> $roles
     * @param Collection<int, ResourcePermission> $resourcePermissions
     */
    private function renderMatrix(Collection $roles, Collection $resourcePermissions): string
    {
        $resourceList = $resourcePermissions->values();

        // Precompute current permissions per role sekali saja, dipakai buat cek status "semua"
        $roleCurrent = [];
        foreach ($roles as $role) {
            $roleCurrent[$role->id] = $this->decodePermissions($role->permissions);
        }

        // Background di-set lewat JS (baca warna aktual di sekitar tabel), bukan tebak nama
        // CSS variable MoonShine — supaya pasti sama persis dengan background card/konten,
        // bukan navbar atau layer lain.
        $stickyColStyle = 'position:sticky;left:0;z-index:3;min-width:140px;';
        $stickyAllStyle = 'position:sticky;left:140px;z-index:3;min-width:70px;';

        $head = '<th class="rp-sticky" style="text-align:left;padding:.6rem;' . $stickyColStyle . '">Role</th>';
        $head .= '<th class="rp-sticky" style="padding:.6rem;text-align:center;white-space:nowrap;' . $stickyAllStyle . '">Semua</th>';

        foreach ($resourceList as $i => $rp) {
            $allRolesHaveThis = $roles->isNotEmpty() && $roles->every(
                fn ($role) => in_array($rp->resource_class, $roleCurrent[$role->id], true)
            );
            $colChecked = $allRolesHaveThis ? 'checked' : '';

            $head .= '<th style="padding:.6rem;text-align:center;white-space:nowrap;">'
                . e($rp->label)
                . '<br><label style="display:inline-flex;align-items:center;justify-content:center;gap:.3rem;'
                . 'font-weight:normal;font-size:.7rem;opacity:.75;">'
                . '<input type="checkbox" class="select-all-col" data-col="' . $i . '" ' . $colChecked . ' '
                . 'onclick="var c=this.checked; document.querySelectorAll(\'.res-cb-col-' . $i . '\').forEach(function(cb){cb.checked=c;}); syncRolePermSelectAll();">'
                . '<span>semua</span>'
                . '</label>'
                . '</th>';
        }

        $rowsHtml = '';
        foreach ($roles as $role) {
            $current = $roleCurrent[$role->id];

            $allResourcesChecked = $resourceList->isNotEmpty() && $resourceList->every(
                fn ($rp) => in_array($rp->resource_class, $current, true)
            );
            $rowChecked = $allResourcesChecked ? 'checked' : '';

            $rowsHtml .= '<tr>';
            $rowsHtml .= '<td class="rp-sticky" style="padding:.6rem;font-weight:600;white-space:nowrap;' . $stickyColStyle . '">' . e($role->name) . '</td>';
            $rowsHtml .= '<td class="rp-sticky" style="text-align:center;padding:.6rem;' . $stickyAllStyle . '">'
                . '<input type="checkbox" class="select-all-row" ' . $rowChecked . ' '
                . 'onclick="var c=this.checked; this.closest(\'tr\').querySelectorAll(\'.res-cb\').forEach(function(cb){cb.checked=c;}); syncRolePermSelectAll();">'
                . '</td>';

            foreach ($resourceList as $i => $rp) {
                $checked = in_array($rp->resource_class, $current, true) ? 'checked' : '';
                $rowsHtml .= '<td style="text-align:center;padding:.6rem;">'
                    . '<input type="checkbox" class="res-cb res-cb-col-' . $i . '" name="permissions[' . $role->id . '][]" value="' . e($rp->resource_class) . '" ' . $checked . ' '
                    . 'onclick="syncRolePermSelectAll();">'
                    . '</td>';
            }

            $rowsHtml .= '</tr>';
        }

        $script = <<<'JS'
            <script>
            function syncRolePermSelectAll() {
                document.querySelectorAll('.select-all-row').forEach(function (rowCb) {
                    var boxes = rowCb.closest('tr').querySelectorAll('.res-cb');
                    rowCb.checked = boxes.length > 0 && Array.prototype.every.call(boxes, function (cb) { return cb.checked; });
                });
                document.querySelectorAll('.select-all-col').forEach(function (colCb) {
                    var boxes = document.querySelectorAll('.res-cb-col-' + colCb.getAttribute('data-col'));
                    colCb.checked = boxes.length > 0 && Array.prototype.every.call(boxes, function (cb) { return cb.checked; });
                });
            }

            function rolePermEffectiveBg(el) {
                while (el && el !== document.documentElement) {
                    var bg = getComputedStyle(el).backgroundColor;
                    if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') { return bg; }
                    el = el.parentElement;
                }
                return getComputedStyle(document.body).backgroundColor || '#fff';
            }

            function syncRolePermStickyBg() {
                var wrap = document.getElementById('rp-matrix-wrap');
                if (!wrap) { return; }
                var bg = rolePermEffectiveBg(wrap.parentElement || wrap);
                document.querySelectorAll('.rp-sticky').forEach(function (el) { el.style.backgroundColor = bg; });
            }

            function syncRolePermStickyBgDuring(durationMs) {
                var start = performance.now();
                function step(now) {
                    syncRolePermStickyBg();
                    if (now - start < durationMs) { requestAnimationFrame(step); }
                }
                requestAnimationFrame(step);
            }

            // Initial load: class/atribut tema MoonShine bisa jadi baru diterapkan SETELAH
            // script ini mulai jalan (mis. saat Alpine masih inisialisasi), jadi baca sekali
            // di awal saja bisa kena state lama (belum dark). Baca ulang tiap frame sesaat
            // supaya ikut ter-koreksi begitu class tema benar-benar terpasang.
            syncRolePermStickyBg();
            syncRolePermStickyBgDuring(500);

            window.addEventListener('darkMode:toggle', function () {
                // MoonShine animasikan transisi warna tema (CSS transition). Baca & terapkan
                // ulang tiap frame selama durasi transisi, supaya warna sticky ikut bertransisi
                // halus bareng elemen lain — bukan salah sesaat lalu "lompat" ke warna benar.
                syncRolePermStickyBgDuring(500);
            });
            </script>
            JS;

        return '<div id="rp-matrix-wrap" style="overflow-x:auto;">'
            . '<table style="width:100%;border-collapse:separate;border-spacing:0;">'
            . '<thead><tr>' . $head . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . $script
            . '<div style="margin-top:1rem;"><button type="submit" class="btn btn-primary">Simpan</button></div>';
    }

    /**
     * @return list<string>
     */
    private function decodePermissions(mixed $permissions): array
    {
        if (is_string($permissions)) {
            return json_decode($permissions, true) ?? [];
        }

        return is_array($permissions) ? $permissions : [];
    }

    #[AsyncMethod]
    public function addResource(): JsonResponse
    {
        abort_unless($this->isAuthorized(), 403, 'Anda tidak memiliki akses untuk melakukan aksi ini.');

        $resourceClass = (string) request()->input('resource_class');
        $label         = trim((string) request()->input('label'));

        $candidates = $this->availableResourceCandidates(ResourcePermission::all());

        if ($resourceClass === '' || $label === '' || ! array_key_exists($resourceClass, $candidates)) {
            session()->flash('resource_manage_alert', ['type' => 'error', 'message' => 'Resource atau label tidak valid.']);

            return JsonResponse::make()->redirect($this->getRoute());
        }

        ResourcePermission::create([
            'resource_class' => $resourceClass,
            'label'          => $label,
        ]);

        MoonShineServiceProvider::forgetManageableResourcesCache();

        session()->flash('resource_manage_alert', ['type' => 'success', 'message' => 'Resource berhasil ditambahkan.']);

        return JsonResponse::make()->redirect($this->getRoute());
    }

    #[AsyncMethod]
    public function removeResource(): JsonResponse
    {
        abort_unless($this->isAuthorized(), 403, 'Anda tidak memiliki akses untuk melakukan aksi ini.');

        $resourceId = (int) request()->input('resource_id');

        ResourcePermission::whereKey($resourceId)->delete();

        MoonShineServiceProvider::forgetManageableResourcesCache();

        session()->flash('resource_manage_alert', ['type' => 'success', 'message' => 'Resource berhasil dihapus.']);

        return JsonResponse::make()->redirect($this->getRoute());
    }

    #[AsyncMethod]
    public function save(): JsonResponse
    {
        abort_unless($this->isAuthorized(), 403, 'Anda tidak memiliki akses untuk melakukan aksi ini.');

        $input   = request()->input('permissions', []);
        $allowed = ResourcePermission::pluck('resource_class')->all();
        $roleIds = MoonshineUserRole::where('id', '!=', MoonshineUserRole::DEFAULT_ROLE_ID)->pluck('id');

        foreach ($roleIds as $roleId) {
            $selected = array_values(array_intersect($input[$roleId] ?? [], $allowed));

            DB::table('moonshine_user_roles')
                ->where('id', $roleId)
                ->update(['permissions' => empty($selected) ? null : json_encode($selected)]);
        }

        toast('Hak akses role berhasil disimpan.', ToastType::SUCCESS);

        return JsonResponse::make()->html([
            '.role-permissions-result' => (string) Alert::make(type: 'success')
                ->content('Pengaturan berhasil disimpan.'),
        ]);
    }
}
