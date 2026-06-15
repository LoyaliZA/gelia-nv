<div id="modal-pin" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-dark-800 border border-dark-700 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform scale-95 transition-transform">
        <h3 class="text-lg font-bold text-white mb-4 text-center">Seguridad Requerida</h3>
        <p class="text-sm text-dark-muted text-center mb-6">Ingresa el PIN maestro para modificar los algoritmos de precios.</p>
        <input type="password" id="input-pin" class="w-full bg-dark-900 border border-dark-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-purple-500 text-center text-xl tracking-widest mb-6" placeholder="••••" maxlength="4">
        <div class="flex gap-3">
            <button onclick="cerrarModalPin()" class="flex-1 py-3 bg-dark-700 hover:bg-dark-600 text-white font-bold rounded-xl transition">Cancelar</button>
            <button onclick="verificarPin()" class="flex-1 py-3 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl transition">Ingresar</button>
        </div>
    </div>
</div>

<div id="modal-config" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-dark-800 border border-purple-500/30 rounded-2xl w-full max-w-4xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="p-6 border-b border-dark-700 flex justify-between items-center">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <span class="bg-purple-500 w-2 h-6 rounded"></span> Algoritmo de Precios
            </h3>
            <button onclick="cerrarModalConfig()" class="text-gray-400 hover:text-white transition">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="p-6 overflow-y-auto custom-scroll flex-1">
            <form id="form-config">
                @csrf
                <div class="mb-8 p-4 bg-dark-900 border border-dark-700 rounded-xl flex items-center justify-between">
                    <div>
                        <label class="block text-white font-bold mb-1">Impuesto al Valor Agregado (IVA)</label>
                        <p class="text-xs text-dark-muted">Valor actual para el cálculo de subtotal.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-purple-400 font-bold">÷</span>
                        <input type="number" step="0.01" name="iva" value="{{ $iva }}" class="w-24 bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white text-center font-bold focus:outline-none focus:border-purple-500">
                    </div>
                </div>

                <h4 class="text-white font-bold mb-4 font-mono uppercase text-xs tracking-widest text-purple-400">Escalones de Ganancia</h4>
                <div class="border border-dark-700 rounded-xl overflow-hidden">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-400 bg-dark-900 uppercase">
                            <tr>
                                <th class="px-4 py-3">Rango de Costo ($)</th>
                                <th class="px-4 py-3 text-center">Mult. Rebaja</th>
                                <th class="px-4 py-3 text-center">Mult. Normal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-dark-700 bg-dark-800">
                            @foreach($margenes as $margen)
                            <tr class="hover:bg-dark-700/30 transition-colors">
                                <td class="px-4 py-3 font-medium text-gray-300">
                                    De ${{ number_format($margen->precio_min, 2) }} a {{ $margen->precio_max >= 99999 ? '∞' : '$'.number_format($margen->precio_max, 2) }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <input type="number" step="0.01" name="margenes[{{ $margen->id }}][rebaja]" value="{{ $margen->multiplicador_rebaja }}" class="w-20 bg-dark-900 border border-dark-600 rounded px-2 py-1 text-white text-center focus:outline-none focus:border-purple-500">
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <input type="number" step="0.01" name="margenes[{{ $margen->id }}][normal]" value="{{ $margen->multiplicador_normal }}" class="w-20 bg-dark-900 border border-dark-600 rounded px-2 py-1 text-white text-center focus:outline-none focus:border-purple-500">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <h4 class="text-white font-bold mt-8 mb-4 font-mono uppercase text-xs tracking-widest text-purple-400">Notificaciones de Sincronización</h4>
                <div class="p-4 bg-dark-900 border border-dark-700 rounded-xl space-y-4">
                    <div>
                        <label class="block text-white font-bold mb-1">Correo del Administrador (Admin)</label>
                        <p class="text-xs text-dark-muted mb-2">Recibe alertas de fallos y confirmaciones de éxito con el reporte.</p>
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <input type="email" name="admin_email" value="{{ $adminEmail ?? '' }}" placeholder="admin@tudominio.com" class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-purple-500 transition-colors">
                        </div>
                    </div>

                    <div class="border-t border-dark-700 pt-4">
                        <label class="block text-white font-bold mb-1">Correos Adicionales (Equipo)</label>
                        <p class="text-xs text-dark-muted mb-2">Reciben el CSV adjunto <span class="text-green-400 font-bold">solo cuando la carga es EXITOSA</span>. (Sepáralos con comas).</p>
                        <div class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-gray-400 mt-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <textarea name="notify_emails" rows="2" placeholder="ventas@ejemplo.com, bodega@ejemplo.com" class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-purple-500 transition-colors custom-scroll">{{ $notifyEmails ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="p-6 border-t border-dark-700 bg-dark-900/50 flex justify-end gap-4 rounded-b-2xl">
            <button onclick="cerrarModalConfig()" class="px-6 py-2.5 bg-dark-700 hover:bg-dark-600 text-white font-bold rounded-xl transition">Cancelar</button>
            <button onclick="guardarConfiguracion()" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl shadow-lg shadow-purple-500/20 transition">Guardar Cambios</button>
        </div>
    </div>
</div>

<div id="modal-progreso" class="fixed inset-0 z-[60] hidden bg-black/90 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-dark-800 border border-purple-500/50 rounded-2xl p-8 w-full max-w-lg shadow-2xl text-center">
        <div class="mb-6 relative inline-block">
            <svg class="animate-spin h-16 w-16 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <h3 class="text-2xl font-bold text-white mb-2">Sincronizando Tienda</h3>
        <p class="text-dark-muted mb-8" id="status-texto">Actualizando precios en tiempo real...</p>

        <div class="w-full bg-dark-900 rounded-full h-4 mb-4 overflow-hidden border border-dark-700">
            <div id="barra-progreso" class="bg-gradient-to-r from-purple-600 to-indigo-600 h-full transition-all duration-500" style="width: 0%"></div>
        </div>

        <div class="flex justify-between text-xs font-bold text-dark-muted uppercase tracking-widest">
            <span id="conteo-productos">0 / 0</span>
            <span id="porcentaje-texto">0%</span>
        </div>

        <button onclick="cerrarModalProgreso()" class="mt-8 text-sm text-purple-400 hover:text-white underline transition">Ocultar ventana</button>
    </div>
</div>

<div id="modal-edicion" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center">
    <div class="bg-dark-800 border border-dark-700 rounded-2xl p-6 w-full max-w-sm shadow-2xl relative">
        <h3 class="text-lg font-bold text-white mb-4">Editar SKU: <span id="edit-sku" class="text-blue-400"></span></h3>

        <form id="form-edicion" class="space-y-4">
            <input type="hidden" id="edit-id">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Precio Normal ($)</label>
                <input type="number" step="0.01" id="edit-normal" class="w-full bg-dark-900 border border-dark-600 rounded-xl px-4 py-2 text-white focus:border-blue-500 outline-none transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Precio Oferta ($)</label>
                <input type="number" step="0.01" id="edit-rebaja" class="w-full bg-dark-900 border border-dark-600 rounded-xl px-4 py-2 text-white focus:border-blue-500 outline-none transition-colors">
            </div>
            <div class="flex gap-3 mt-6 pt-4 border-t border-dark-700">
                <button type="button" onclick="cerrarModalEdicion()" class="flex-1 py-2 bg-dark-700 text-gray-300 rounded-xl hover:bg-dark-600 font-bold transition-colors">Cancelar</button>
                <button type="button" onclick="guardarEdicionIndividual()" class="flex-1 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-500 font-bold shadow-lg transition-colors">Actualizar Web</button>
            </div>
        </form>
    </div>
</div>