@extends('admin.layouts.app')
@section('page_title','Version Upgrading')
@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-end">
                <div class="col-sm mb-2 mb-sm-0">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-no-gutter">
                            <li class="breadcrumb-item">
                                <a class="breadcrumb-link" href="javascript:void(0)">@lang('Dashboard')</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">@lang('Settings')</li>
                            <li class="breadcrumb-item active" aria-current="page">@lang('Version Upgrading')</li>
                        </ol>
                    </nav>
                    <h1 class="page-header-title">@lang('Version Upgrading')</h1>
                </div>
            </div>
        </div>

        <div class="row">
			<div class="col-lg-12">
				<div class="alert alert-soft-dark" role="alert">
					<div class="d-flex align-items-baseline">
						<div class="flex-shrink-0">
							<i class="bi-info-circle me-1"></i>
						</div>

						<div class="flex-grow-1 ms-2">
							<h3>Read this Before Upgrading</h3>
							Do <span class="text-dark fw-semibold">not</span> forget to <span
								class="text-dark fw-semibold">backup</span>
							your files & Database and <span class="text-dark fw-semibold">read the changelog</span>
							before updating
							your
							Front copy. If you come across with any problems with Front template during the update and
							development processes, feel free to contact our support group at <a class="link"
																								href="https://bugfinder.net/user/support-ticket/new"
																								target="_blank">https://bugfinder.net/user/support-ticket/new
								<i class="fas fa-external-link-alt fa-xs"></i></a> and our team will assist you within a
							short
							time. If you done <span class="text-dark fw-semibold"> modify or customize </span> any files
							before, version updates may be affected on your
							site. For this reason, please contact our support team <a
								href="https://bugfinder.net/user/support-ticket/new" target="_blank" class="link">here
								<i class="fal fa-external-link"></i></a>
						</div>
					</div>
				</div>
			</div>
			<div class="col-lg-3">
				@include('admin.control_panel.components.sidebar', ['settings' => config('generalsettings.settings'), 'suffix' => 'Settings'])
			</div>
            <div class="col-lg-9" id="basic_control">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="card-title h4">@lang('Version Upgrading')</h2>
                            <div>
                                <a href="{{ route('admin.download.server.files') }}" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Server File</a>
                                <a href="{{ route('admin.download.db') }}" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Server Database</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-soft-dark card-alert text-center" role="alert">
                            Your Current version: <span class="text-dark fw-semibold"> {{ $current }} |</span>
                            <a href="#" id="check-btn"><i class="fas fa-hand-point-right"></i> Check new Update</a>
                        </div>
                        <div class="form-check my-3">
                            <input type="checkbox" id="auto-backup" class="form-check-input" checked>
                            <label class="form-check-label" for="auto-backup">Auto backup before update</label>
                        </div>
                        <div id="update-box"><div id="update-result" style="margin-top:15px;"></div></div>
                        <h5 class="mt-4">Update Progress</h5>
                        <pre id="progress-log" style="background:#f7f7f7; padding:12px; height:180px; overflow:auto;">Idle</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('script')
<script>
    'use strict';
    const csrf = '{{ csrf_token() }}';
    let lastStatus = "";
    let lastMessage = "";
    function setProgress(text){ document.getElementById('progress-log').textContent = text; }
    function pollStatus(){
        fetch("{{ route('admin.updates.status') }}",{method:'POST',headers:{'X-CSRF-TOKEN':csrf}})
        .then(r=>r.json()).then(data=>{ let status=data.status||'Idle'; let msg=data.message||''; if(status===lastStatus && msg===lastMessage){ return setTimeout(pollStatus,2000);} lastStatus=status; lastMessage=msg; setProgress(`[${status}] ${msg}`); setTimeout(pollStatus,2000); }).catch(()=>setTimeout(pollStatus,3000));
    }
    document.getElementById('check-btn').addEventListener('click', function () {
        const btn = this; btn.disabled = true; btn.textContent = 'Checking...';
        fetch("{{ route('admin.updates.check') }}",{method:'POST',headers:{'X-CSRF-TOKEN':csrf}})
        .then(r=>r.json()).then(data=>{ btn.disabled=false; btn.textContent='Check for Update'; if(data.status==='success'){ const latest=data.latest; if(latest && latest.latest_version && parseFloat(latest.latest_version) > parseFloat(data.current_version)){ const container=document.getElementById('update-result'); container.innerHTML = `<div>Latest Version: <strong>${latest.latest_version}</strong> | Release Date: ${latest.release_date || ''}</div><pre>${latest.update_log || ''}</pre><button id="install-btn" class="btn btn-success mt-2">Download & Install</button>`; document.getElementById('install-btn').addEventListener('click', function () { const installBtn = this; installBtn.disabled = true; installBtn.textContent = 'Installing...'; const autoBackup = document.getElementById('auto-backup').checked ? 1 : 0; fetch("{{ route('admin.updates.install') }}",{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json'},body:JSON.stringify({auto_backup:autoBackup})}).then(r=>r.json()).then(res=>{ alert(res.message || 'Done'); installBtn.disabled=false; installBtn.textContent='Download & Install'; }).catch(()=>{ alert('Install failed'); installBtn.disabled=false; installBtn.textContent='Download & Install'; }); }); } else { alert('You are already using the latest version.'); }} else { alert(data.message || 'Check failed'); } }).catch(()=>{ btn.disabled=false; btn.textContent='Check for Update'; alert('Request failed'); }); });
    pollStatus();
</script>
@endpush
