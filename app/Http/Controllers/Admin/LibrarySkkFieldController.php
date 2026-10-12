<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLibrarySkkFieldRequest;
use App\Http\Requests\Admin\UpdateLibrarySkkFieldRequest;
use App\Models\LibrarySkkField;
use App\Models\ProjectSkkField;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LibrarySkkFieldController extends Controller
{
    public function index(): Response
    {
        $fields = LibrarySkkField::query()
            ->orderBy('entity_type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30)
            ->through(fn (LibrarySkkField $field) => [
                'id' => $field->id,
                'name' => $field->name,
                'entity_type' => $field->entity_type,
                'entity_label' => ProjectSkkField::TYPE_LABELS[$field->entity_type] ?? $field->entity_type,
                'value_type_label' => ProjectSkkField::VALUE_TYPE_LABELS[$field->value_type] ?? $field->value_type,
                'stage_name' => $field->stage_name,
                'edit_url' => route('admin.library.skk-fields.edit', $field),
                'destroy_url' => route('admin.library.skk-fields.destroy', $field),
            ]);

        return Inertia::render('Admin/Library/SkkFields/Index', [
            'fields' => $fields,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Library/SkkFields/Create', [
            'entityOptions' => $this->entityOptions(),
            'valueTypeOptions' => $this->valueTypeOptions(),
        ]);
    }

    public function store(StoreLibrarySkkFieldRequest $request): RedirectResponse
    {
        LibrarySkkField::query()->create($request->fieldPayload());

        return redirect()
            ->route('admin.library.skk-fields.index')
            ->with('success', 'Шаблон поля СКК создан.');
    }

    public function edit(LibrarySkkField $librarySkkField): Response
    {
        return Inertia::render('Admin/Library/SkkFields/Edit', [
            'field' => [
                'id' => $librarySkkField->id,
                'entity_type' => $librarySkkField->entity_type,
                'name' => $librarySkkField->name,
                'value_type' => $librarySkkField->value_type,
                'is_required' => $librarySkkField->is_required,
                'show_in_card' => $librarySkkField->show_in_card,
                'show_in_list' => $librarySkkField->show_in_list,
                'show_in_filter' => $librarySkkField->show_in_filter,
                'options' => $librarySkkField->options ?? [],
                'stage_name' => $librarySkkField->stage_name ?? '',
                'comment' => $librarySkkField->comment ?? '',
                'sort_order' => $librarySkkField->sort_order,
                'update_url' => route('admin.library.skk-fields.update', $librarySkkField),
                'destroy_url' => route('admin.library.skk-fields.destroy', $librarySkkField),
            ],
            'entityOptions' => $this->entityOptions(),
            'valueTypeOptions' => $this->valueTypeOptions(),
        ]);
    }

    public function update(UpdateLibrarySkkFieldRequest $request, LibrarySkkField $librarySkkField): RedirectResponse
    {
        $librarySkkField->update($request->fieldPayload());

        return redirect()
            ->route('admin.library.skk-fields.index')
            ->with('success', 'Шаблон поля СКК обновлён.');
    }

    public function destroy(LibrarySkkField $librarySkkField): RedirectResponse
    {
        $librarySkkField->delete();

        return redirect()
            ->route('admin.library.skk-fields.index')
            ->with('success', 'Шаблон поля СКК удалён.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function entityOptions(): array
    {
        return collect(ProjectSkkField::TYPE_LABELS)
            ->map(fn (string $label, string $value) => compact('value', 'label'))
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function valueTypeOptions(): array
    {
        return collect(ProjectSkkField::VALUE_TYPE_LABELS)
            ->map(fn (string $label, string $value) => compact('value', 'label'))
            ->values()
            ->all();
    }
}
