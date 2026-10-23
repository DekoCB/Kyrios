@if(session('success'))
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
        <p class="font-semibold mb-1"><i class="fas fa-exclamation-circle mr-2"></i>Revise estos datos:</p>
        <ul class="list-disc list-inside text-sm">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif
