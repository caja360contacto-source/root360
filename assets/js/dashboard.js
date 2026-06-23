async function cargarResumen() {
  const r = await fetch('api/resumen.php');
  const data = await r.json();

  document.getElementById('stat-mensajes').textContent = data.mensajes_nuevos;
  document.getElementById('stat-clientes').textContent = data.total_clientes;
  document.getElementById('stat-suscripciones').textContent = data.suscripciones_activas;
  document.getElementById('stat-tickets').textContent = data.tickets_abiertos;
  document.getElementById('nav-badge-mensajes').textContent = data.mensajes_nuevos;
  document.getElementById('nav-badge-mensajes').style.display = data.mensajes_nuevos > 0 ? 'inline-block' : 'none';

  const basico = data.servicios_instalacion.basico || 0;
  const completo = data.servicios_instalacion.completo || 0;
  const totalInstal = basico + completo || 1;
  const suscripciones = data.suscripciones_activas;
  const totalConSuscr = Math.max(totalInstal, suscripciones, 1);

  document.getElementById('serv-completo-val').textContent = completo;
  document.getElementById('serv-basico-val').textContent = basico;
  document.getElementById('serv-suscripcion-val').textContent = suscripciones;

  document.getElementById('serv-completo-bar').style.width = (completo / totalInstal * 100) + '%';
  document.getElementById('serv-basico-bar').style.width = (basico / totalInstal * 100) + '%';
  document.getElementById('serv-suscripcion-bar').style.width = (suscripciones / totalConSuscr * 100) + '%';

  renderProximosCobros(data.proximos_cobros);
}

function renderProximosCobros(lista) {
  const cont = document.getElementById('lista-cobros');
  if (!lista.length) {
    cont.innerHTML = '<div class="empty">No hay cobros de mantenimiento próximos</div>';
    return;
  }
  cont.innerHTML = lista.map(c => {
    const dias = c.dias_restantes;
    let claseDias = '';
    let texto = `en ${dias} días`;
    if (dias < 0) { claseDias = 'urgente'; texto = `vencido hace ${Math.abs(dias)} días`; }
    else if (dias === 0) { claseDias = 'urgente'; texto = 'hoy'; }
    else if (dias <= 3) { claseDias = 'urgente'; }
    else if (dias <= 7) { claseDias = 'pronto'; }
    return `
      <div class="row">
        <div>
          <span class="nombre">${escapeHtml(c.nombre_negocio)}</span>
          <span class="email">${escapeHtml(c.nombre_responsable)} · ${formatFecha(c.proximo_cobro)}</span>
        </div>
        <div class="dias ${claseDias}">${texto}</div>
        <div style="font-family:var(--mono);font-size:12px;">$${Number(c.precio_mantenimiento || 0).toLocaleString('es-AR')}</div>
      </div>
    `;
  }).join('');
}

async function cargarMensajes() {
  const r = await fetch('api/contactos.php?estado=nuevo');
  const data = await r.json();
  const cont = document.getElementById('lista-mensajes');

  if (!data.contactos.length) {
    cont.innerHTML = '<div class="empty">No hay mensajes nuevos de la landing</div>';
    return;
  }

  cont.innerHTML = data.contactos.map(m => `
    <div class="row row-clickable" style="grid-template-columns:1fr auto;"
         onclick="abrirChat('${m.id}', '${escapeHtml(m.nombre).replace(/'/g,"\\'")}', '${escapeHtml(m.email)}', '${m.id}')">
      <div>
        <span class="nombre">${escapeHtml(m.nombre)}</span>
        <span class="email">${escapeHtml(m.email)} · ${formatFechaHora(m.creado_en)}</span>
        ${m.mensaje ? `<span class="msg">"${escapeHtml(m.mensaje)}"</span>` : ''}
      </div>
      <select class="estado-select" data-id="${m.id}" onchange="actualizarEstadoContacto(this)" onclick="event.stopPropagation()">
        <option value="nuevo" selected>Nuevo</option>
        <option value="contactado">Contactado</option>
        <option value="convertido">Convertido</option>
        <option value="descartado">Descartado</option>
      </select>
    </div>
  `).join('');
}

function renderFilaMensaje(m) {
  return `
    <div class="row row-clickable" style="grid-template-columns:1fr auto auto;"
         onclick="abrirChat('${m.id}', '${escapeHtml(m.nombre).replace(/'/g,"\\'")}', '${escapeHtml(m.email)}', '${m.id}')">
      <div>
        <span class="nombre">${escapeHtml(m.nombre)}</span>
        <span class="email">${escapeHtml(m.email)} · ${formatFechaHora(m.creado_en)}</span>
        ${m.mensaje ? `<span class="msg">"${escapeHtml(m.mensaje)}"</span>` : ''}
      </div>
      <div class="badge ${m.estado}">${m.estado}</div>
      <select class="estado-select" data-id="${m.id}" onchange="actualizarEstadoContacto(this)" onclick="event.stopPropagation()">
        <option value="nuevo" ${m.estado === 'nuevo' ? 'selected' : ''}>Nuevo</option>
        <option value="contactado" ${m.estado === 'contactado' ? 'selected' : ''}>Contactado</option>
        <option value="convertido" ${m.estado === 'convertido' ? 'selected' : ''}>Convertido</option>
        <option value="descartado" ${m.estado === 'descartado' ? 'selected' : ''}>Descartado</option>
      </select>
    </div>
  `;
}

async function cargarMensajesFull() {
  const filtro = document.getElementById('filtro-estado-mensajes').value;
  const url = 'api/contactos.php' + (filtro ? ('?estado=' + filtro) : '');
  const r = await fetch(url);
  const data = await r.json();
  const cont = document.getElementById('lista-mensajes-full');

  if (!data.contactos.length) {
    cont.innerHTML = '<div class="empty">No hay mensajes para mostrar</div>';
    return;
  }
  cont.innerHTML = data.contactos.map(renderFilaMensaje).join('');
}

async function actualizarEstadoContacto(select) {
  const id = select.dataset.id;
  const estado = select.value;
  await fetch('api/contactos.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, estado })
  });
  cargarMensajes();
  cargarMensajesFull();
  cargarResumen();
}

// ─── Chat ────────────────────────────────────────────────────────────────────

let chatActual = { email: null, nombre: null, ultimoMessageId: null, contactoId: null };

async function abrirChat(contactoId, nombre, email, origenId) {
  chatActual = { email, nombre, ultimoMessageId: null, contactoId: origenId || contactoId };

  document.getElementById('chat-nombre').textContent = nombre;
  document.getElementById('chat-email').textContent = email;
  document.getElementById('chat-overlay').classList.add('open');
  document.getElementById('chat-body').innerHTML = '<div class="empty">Cargando conversación...</div>';

  await cargarHiloChat();
}

function cerrarChat() {
  document.getElementById('chat-overlay').classList.remove('open');
}

function cerrarChatSiFondo(ev) {
  if (ev.target.id === 'chat-overlay') cerrarChat();
}

async function cargarHiloChat() {
  const cont = document.getElementById('chat-body');
  try {
    let url = 'api/chat_mensajes.php?email=' + encodeURIComponent(chatActual.email);
    if (chatActual.contactoId) {
      url += '&contacto_id=' + encodeURIComponent(chatActual.contactoId);
    }

    const r = await fetch(url);
    const data = await r.json();

    if (data.error) {
      cont.innerHTML = `<div class="empty" style="color:var(--warn);">${escapeHtml(data.error)}</div>`;
      return;
    }

    if (!data.mensajes.length) {
      cont.innerHTML = '<div class="empty">Todavía no hay mensajes con este contacto.</div>';
      return;
    }

    chatActual.ultimoMessageId = data.mensajes[data.mensajes.length - 1].message_id;

    cont.innerHTML = data.mensajes.map(m => {
      const esMio = m.direccion === 'saliente';
      const esLanding = m.origen === 'landing';
      return `
        <div class="bubble-row ${esMio ? 'mio' : ''}">
          <div class="bubble">
            ${esLanding ? '<div style="font-size:10px;opacity:.6;margin-bottom:4px;">📋 Formulario de la landing</div>' : ''}
            <div class="bubble-text">${escapeHtml(m.cuerpo).replace(/\n/g, '<br>')}</div>
            <div class="bubble-time">${formatFechaHora(m.fecha)}</div>
          </div>
        </div>
      `;
    }).join('');

    cont.scrollTop = cont.scrollHeight;
  } catch (err) {
    cont.innerHTML = `<div class="empty" style="color:var(--warn);">Error al cargar: ${escapeHtml(err.message)}</div>`;
  }
}

async function enviarChat(ev) {
  ev.preventDefault();
  const input = document.getElementById('chat-input');
  const texto = input.value.trim();
  if (!texto) return;

  const btn = document.getElementById('chat-send-btn');
  btn.disabled = true;
  btn.textContent = 'Enviando...';

  try {
    const r = await fetch('api/chat_enviar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: chatActual.email,
        asunto: 'Re: Consulta Caja360 - ' + chatActual.nombre,
        mensaje: texto,
        en_respuesta_a: chatActual.ultimoMessageId
      })
    });
    const data = await r.json();
    if (!r.ok || data.error) throw new Error(data.error || 'No se pudo enviar');

    input.value = '';
    await cargarHiloChat();
  } catch (err) {
    alert('Error al enviar: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Enviar';
  }
}

async function cargarClientes() {
  const r = await fetch('api/clientes.php');
  const data = await r.json();
  const cont = document.getElementById('lista-clientes');

  if (!data.clientes.length) {
    cont.innerHTML = '<div class="empty">Todavía no hay clientes cargados</div>';
    return;
  }

  cont.innerHTML = data.clientes.map(c => `
    <div class="row" style="grid-template-columns:1fr auto auto;">
      <div>
        <span class="nombre">${escapeHtml(c.nombre_negocio)}</span>
        <span class="email">${escapeHtml(c.nombre_responsable)} · ${escapeHtml(c.email)}</span>
      </div>
      <div class="badge ${c.estado}">${c.estado.replace('_',' ')}</div>
      <div style="font-family:var(--mono);font-size:12px;color:#8a8473;">
        ${c.tickets_abiertos > 0 ? c.tickets_abiertos + ' ticket(s)' : '—'}
      </div>
    </div>
  `).join('');
}

// ─── Utilidades ──────────────────────────────────────────────────────────────

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
function formatFecha(d) {
  if (!d) return '—';
  const f = new Date(d + 'T00:00:00');
  return f.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
}
function formatFechaHora(d) {
  if (!d) return '—';
  const f = new Date(d.replace(' ', 'T'));
  return f.toLocaleDateString('es-AR', { day: '2-digit', month: 'short' }) + ' ' +
         f.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
}

function mostrarSeccion(seccion) {
  document.querySelectorAll('.seccion').forEach(s => s.style.display = 'none');
  document.getElementById('seccion-' + seccion).style.display = 'block';
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('nav-' + seccion).classList.add('active');

  if (seccion === 'mensajes') cargarMensajesFull();
  if (seccion === 'clientes') cargarClientes();
}

// ─── Alta de cliente ──────────────────────────────────────────────────────────

function toggleMantenimiento() {
  const activo = document.getElementById('chk-mantenimiento').checked;
  document.getElementById('campo-precio-mant').style.display = activo ? 'flex' : 'none';
  document.getElementById('campo-proximo-cobro').style.display = activo ? 'flex' : 'none';
}

async function abrirFormCliente() {
  document.getElementById('panel-form-cliente').style.display = 'block';
  document.getElementById('form-cliente').reset();
  toggleMantenimiento();
  await cargarContactosConvertibles();
}

function cerrarFormCliente() {
  document.getElementById('panel-form-cliente').style.display = 'none';
}

async function cargarContactosConvertibles() {
  const r = await fetch('api/contactos.php');
  const data = await r.json();
  const select = document.getElementById('select-contacto-origen');
  const disponibles = data.contactos.filter(c => c.estado !== 'convertido' && c.estado !== 'descartado');
  select.innerHTML = '<option value="">— ninguno / alta directa —</option>' +
    disponibles.map(c => `<option value="${c.id}">${escapeHtml(c.nombre)} · ${escapeHtml(c.email)}</option>`).join('');
}

async function guardarCliente(ev) {
  ev.preventDefault();
  const form = ev.target;
  const fd = new FormData(form);

  const payload = {
    contacto_id:          fd.get('contacto_id') || null,
    nombre_negocio:       fd.get('nombre_negocio'),
    nombre_responsable:   fd.get('nombre_responsable'),
    email:                fd.get('email'),
    telefono:             fd.get('telefono'),
    direccion:            fd.get('direccion'),
    localidad:            fd.get('localidad'),
    plan_instalacion:     fd.get('plan_instalacion'),
    precio_instalacion:   fd.get('precio_instalacion') || 0,
    fecha_instalacion:    fd.get('fecha_instalacion') || null,
    mantenimiento_activo: fd.get('mantenimiento_activo') ? 1 : 0,
    precio_mantenimiento: fd.get('precio_mantenimiento') || null,
    proximo_cobro:        fd.get('proximo_cobro') || null,
    cantidad_cajas:       fd.get('cantidad_cajas') || 1,
    ip_servidor_local:    fd.get('ip_servidor_local'),
    observaciones:        fd.get('observaciones'),
  };

  const btn = form.querySelector('button[type=submit]');
  btn.disabled = true;
  btn.textContent = 'Guardando...';

  try {
    const r = await fetch('api/clientes.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await r.json();
    if (!r.ok || data.error) throw new Error(data.error || 'No se pudo guardar el cliente');

    cerrarFormCliente();
    await cargarClientes();
    await cargarResumen();
    await cargarMensajes();
  } catch (err) {
    alert('Error al guardar: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Guardar cliente';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  cargarResumen();
  cargarMensajes();
  cargarClientes();
  setInterval(() => { cargarResumen(); cargarMensajes(); }, 30000);
});
