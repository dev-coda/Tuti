@extends('layouts.admin')

@section('title', 'Plantillas de Correo')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Plantillas de Correo</h1>
                <a href="{{ route('email-templates.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva Plantilla
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Slug</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($templates as $template)
                                    <tr>
                                        <td>{{ $template->name }}</td>
                                        <td><code>{{ $template->slug }}</code></td>
                                        <td>
                                            <span class="badge bg-{{ $template->type == 'order_status' ? 'primary' : ($template->type == 'order_confirmation' ? 'success' : ($template->type == 'user_registration' ? 'info' : 'warning')) }}">
                                                {{ $template->getTypes()[$template->type] }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($template->is_active)
                                                <span class="badge bg-success">Activa</span>
                                            @else
                                                <span class="badge bg-secondary">Inactiva</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('email-templates.show', $template) }}"
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="Ver">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('email-templates.edit', $template) }}"
                                                   class="btn btn-sm btn-outline-secondary"
                                                   title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-info preview-btn"
                                                        data-template-id="{{ $template->id }}"
                                                        title="Vista previa">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center">
                        {{ $templates->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vista Previa de Plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><strong>Asunto:</strong></label>
                    <div id="preview-subject" class="form-control-plaintext"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Contenido:</strong></label>
                    <div id="preview-body" class="border p-3 bg-light" style="max-height: 400px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const previewBtns = document.querySelectorAll('.preview-btn');

    previewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const templateId = this.getAttribute('data-template-id');

            fetch(`/admin/email-templates/${templateId}/preview`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('preview-subject').textContent = data.subject;
                document.getElementById('preview-body').innerHTML = data.body;

                const modal = new bootstrap.Modal(document.getElementById('previewModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cargar la vista previa');
            });
        });
    });
});
</script>
@endsection
