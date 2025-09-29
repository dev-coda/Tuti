@extends('layouts.admin')

@section('title', 'Ver Plantilla de Correo')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Plantilla: {{ $template->name }}</h1>
                <div>
                    <a href="{{ route('email-templates.edit', $template) }}" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <a href="{{ route('email-templates.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Detalles de la Plantilla</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Nombre:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $template->name }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Slug:</strong>
                                </div>
                                <div class="col-sm-9">
                                    <code>{{ $template->slug }}</code>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Tipo:</strong>
                                </div>
                                <div class="col-sm-9">
                                    <span class="badge bg-{{ $template->type == 'order_status' ? 'primary' : ($template->type == 'order_confirmation' ? 'success' : ($template->type == 'user_registration' ? 'info' : 'warning')) }}">
                                        {{ $template->getTypes()[$template->type] }}
                                    </span>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Estado:</strong>
                                </div>
                                <div class="col-sm-9">
                                    @if($template->is_active)
                                        <span class="badge bg-success">Activa</span>
                                    @else
                                        <span class="badge bg-secondary">Inactiva</span>
                                    @endif
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Asunto:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $template->subject }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Variables Disponibles:</strong>
                                </div>
                                <div class="col-sm-9">
                                    @if($template->variables)
                                        @foreach($template->variables as $variable)
                                            <span class="badge bg-light text-dark me-1">{{ '{' . $variable . '}' }}</span>
                                        @endforeach
                                    @else
                                        <em class="text-muted">No se han definido variables específicas</em>
                                    @endif
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-3">
                                    <strong>Contenido:</strong>
                                </div>
                                <div class="col-sm-9">
                                    <div class="border p-3 bg-light" style="max-height: 400px; overflow-y: auto;">
                                        {!! nl2br(e($template->body)) !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Vista Previa</h5>
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-info w-100 preview-btn" data-template-id="{{ $template->id }}">
                                <i class="fas fa-search"></i> Ver Vista Previa
                            </button>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Variables Predeterminadas</h5>
                        </div>
                        <div class="card-body">
                            <div class="small">
                                @foreach($template->getDefaultVariables() as $variable)
                                    <span class="badge bg-secondary me-1 mb-1">{{ '{' . $variable . '}' }}</span>
                                @endforeach
                            </div>
                        </div>
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
    const previewBtn = document.querySelector('.preview-btn');

    if (previewBtn) {
        previewBtn.addEventListener('click', function() {
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
    }
});
</script>
@endsection
