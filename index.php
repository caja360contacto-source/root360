<?php
// (Más adelante: acá va el require de una sesión / login antes de mostrar el panel)
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Caja360 · Panel Root</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">

  <aside class="sidebar">
    <div class="brand">CAJA360<span>PANEL ROOT</span></div>

    <div class="nav-item active" id="nav-inicio" onclick="mostrarSeccion('inicio')">
      Inicio
    </div>
    <div class="nav-item" id="nav-mensajes" onclick="mostrarSeccion('mensajes')">
      Mensajes landing
      <span class="nav-badge" id="nav-badge-mensajes" style="display:none;">0</span>
    </div>
    <div class="nav-item" id="nav-clientes" onclick="mostrarSeccion('clientes')">
      Clientes
    </div>
  </aside>

  <main class="main">

    <div class="topbar">
      <h1>Resumen general</h1>
      <div class="fecha" id="fecha-hoy"></div>
    </div>

    <!-- ============ SECCIÓN INICIO ============ -->
    <div class="seccion" id="seccion-inicio">

      <div class="stats-row">
        <div class="ticket warn">
          <div class="label">Mensajes nuevos</div>
          <div class="value" id="stat-mensajes">—</div>
          <div class="sub">desde la landing</div>
        </div>
        <div class="ticket">
          <div class="label">Clientes activos</div>
          <div class="value" id="stat-clientes">—</div>
          <div class="sub">instalaciones en uso</div>
        </div>
        <div class="ticket ok">
          <div class="label">Suscripciones activas</div>
          <div class="value" id="stat-suscripciones">—</div>
          <div class="sub">mantenimiento mensual</div>
        </div>
        <div class="ticket">
          <div class="label">Tickets abiertos</div>
          <div class="value" id="stat-tickets">—</div>
          <div class="sub">soporte pendiente</div>
        </div>
      </div>

      <div class="grid-2">
        <div>
          <div class="panel">
            <div class="panel-head"><h2>Próximos cobros de mantenimiento</h2></div>
            <div class="panel-body" id="lista-cobros">
              <div class="empty">Cargando...</div>
            </div>
          </div>

          <div class="panel">
            <div class="panel-head"><h2>Últimos mensajes de la landing</h2></div>
            <div class="panel-body" id="lista-mensajes">
              <div class="empty">Cargando...</div>
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head"><h2>Servicios vendidos</h2></div>
          <div class="servicios-bars">

            <div class="serv-row">
              <div class="top"><span>Instalación completa</span><b id="serv-completo-val">0</b></div>
              <div class="bar-bg"><div class="bar-fill" id="serv-completo-bar" style="width:0%"></div></div>
            </div>

            <div class="serv-row">
              <div class="top"><span>Instalación básica</span><b id="serv-basico-val">0</b></div>
              <div class="bar-bg"><div class="bar-fill alt" id="serv-basico-bar" style="width:0%"></div></div>
            </div>

            <div class="serv-row">
              <div class="top"><span>Suscripción (mantenimiento)</span><b id="serv-suscripcion-val">0</b></div>
              <div class="bar-bg"><div class="bar-fill" id="serv-suscripcion-bar" style="width:0%"></div></div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- ============ SECCIÓN MENSAJES ============ -->
    <div class="seccion" id="seccion-mensajes" style="display:none;">
      <div class="panel">
        <div class="panel-head">
          <h2>Mensajes de la landing</h2>
          <select id="filtro-estado-mensajes" class="estado-select" onchange="cargarMensajesFull()">
            <option value="">Todos</option>
            <option value="nuevo">Nuevos</option>
            <option value="contactado">Contactados</option>
            <option value="convertido">Convertidos</option>
            <option value="descartado">Descartados</option>
          </select>
        </div>
        <div class="panel-body" id="lista-mensajes-full" style="max-height:none;">
          <div class="empty">Cargando...</div>
        </div>
      </div>
    </div>

    <!-- ============ SECCIÓN CLIENTES ============ -->
    <div class="seccion" id="seccion-clientes" style="display:none;">

      <div class="panel" id="panel-form-cliente" style="display:none;">
        <div class="panel-head">
          <h2>Nuevo cliente</h2>
          <button class="btn-link" onclick="cerrarFormCliente()">cancelar</button>
        </div>
        <form id="form-cliente" class="form-grid" onsubmit="guardarCliente(event)">

          <div class="campo">
            <label>Contacto de origen (opcional)</label>
            <select name="contacto_id" id="select-contacto-origen">
              <option value="">— ninguno / alta directa —</option>
            </select>
          </div>

          <div class="campo">
            <label>Nombre del negocio *</label>
            <input type="text" name="nombre_negocio" required>
          </div>

          <div class="campo">
            <label>Responsable *</label>
            <input type="text" name="nombre_responsable" required>
          </div>

          <div class="campo">
            <label>Email *</label>
            <input type="email" name="email" required>
          </div>

          <div class="campo">
            <label>Teléfono</label>
            <input type="text" name="telefono">
          </div>

          <div class="campo">
            <label>Localidad</label>
            <input type="text" name="localidad">
          </div>

          <div class="campo">
            <label>Dirección</label>
            <input type="text" name="direccion">
          </div>

          <div class="campo">
            <label>Cantidad de cajas</label>
            <input type="number" name="cantidad_cajas" min="1" value="1">
          </div>

          <div class="campo">
            <label>Plan de instalación</label>
            <select name="plan_instalacion">
              <option value="completo">Completo</option>
              <option value="basico">Básico</option>
            </select>
          </div>

          <div class="campo">
            <label>Precio instalación</label>
            <input type="number" name="precio_instalacion" min="0" step="0.01" value="0">
          </div>

          <div class="campo">
            <label>Fecha de instalación</label>
            <input type="date" name="fecha_instalacion">
          </div>

          <div class="campo checkbox-campo">
            <label><input type="checkbox" name="mantenimiento_activo" id="chk-mantenimiento" onchange="toggleMantenimiento()"> Tiene suscripción de mantenimiento</label>
          </div>

          <div class="campo" id="campo-precio-mant" style="display:none;">
            <label>Precio mantenimiento (mensual)</label>
            <input type="number" name="precio_mantenimiento" min="0" step="0.01">
          </div>

          <div class="campo" id="campo-proximo-cobro" style="display:none;">
            <label>Próximo cobro</label>
            <input type="date" name="proximo_cobro">
          </div>

          <div class="campo">
            <label>IP servidor local</label>
            <input type="text" name="ip_servidor_local" placeholder="192.168.1.10">
          </div>

          <div class="campo campo-full">
            <label>Observaciones</label>
            <textarea name="observaciones" rows="2"></textarea>
          </div>

          <div class="campo campo-full" style="text-align:right;">
            <button type="submit" class="btn">Guardar cliente</button>
          </div>

        </form>
      </div>

      <div class="panel">
        <div class="panel-head">
          <h2>Clientes</h2>
          <button class="btn" onclick="abrirFormCliente()">+ Nuevo cliente</button>
        </div>
        <div class="panel-body" id="lista-clientes" style="max-height:none;">
          <div class="empty">Cargando...</div>
        </div>
      </div>
    </div>

  </main>
</div>

<!-- ============ MODAL CHAT ============ -->
<div class="chat-overlay" id="chat-overlay" onclick="cerrarChatSiFondo(event)">
  <div class="chat-modal">
    <div class="chat-header">
      <div>
        <div class="chat-nombre" id="chat-nombre">—</div>
        <div class="chat-email" id="chat-email">—</div>
      </div>
      <button class="btn-link" onclick="cerrarChat()">cerrar ✕</button>
    </div>
    <div class="chat-body" id="chat-body">
      <div class="empty">Cargando conversación...</div>
    </div>
    <form class="chat-footer" onsubmit="enviarChat(event)">
      <textarea id="chat-input" placeholder="Escribí tu respuesta..." rows="2" required></textarea>
      <button type="submit" class="btn" id="chat-send-btn">Enviar</button>
    </form>
  </div>
</div>

<script src="assets/js/dashboard.js"></script>
<script>
  document.getElementById('fecha-hoy').textContent = new Date().toLocaleDateString('es-AR', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
  });
</script>
</body>
</html>
