package com.example.inventario.ui

import android.graphics.Bitmap
import android.graphics.Canvas as AndroidCanvas
import android.graphics.Paint as AndroidPaint
import android.graphics.Path as AndroidPath
import android.graphics.pdf.PdfRenderer
import android.os.ParcelFileDescriptor
import android.util.Base64
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.example.inventario.data.ChoiceDto
import com.example.inventario.data.CreateServiceOrderRequest
import com.example.inventario.data.ServiceOrderDto
import com.example.inventario.ui.theme.NexoDanger
import com.example.inventario.ui.theme.NexoMuted
import com.example.inventario.ui.theme.NexoSuccess
import com.example.inventario.ui.theme.NexoWarning
import java.io.ByteArrayOutputStream
import java.io.File
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

@Composable
fun ServiceOrdersScreen(state: AppUiState, viewModel: AppViewModel) {
    var creating by remember { mutableStateOf(false) }

    Column(
        Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background),
    ) {
        Column(Modifier.padding(start = 18.dp, end = 18.dp, top = 14.dp, bottom = 10.dp)) {
            Text(
                "Gestión de celulares",
                style = MaterialTheme.typography.titleLarge,
                color = MaterialTheme.colorScheme.onBackground,
            )
            Text(
                "Registra equipos y consulta su seguimiento técnico.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        Surface(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
            shape = RoundedCornerShape(16.dp),
            color = MaterialTheme.colorScheme.surface,
            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
            shadowElevation = 2.dp,
        ) {
            Column(
                modifier = Modifier.padding(14.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                OutlinedTextField(
                    value = state.serviceSearch,
                    onValueChange = viewModel::setServiceSearch,
                    label = { Text("Orden, cliente o IMEI") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                )
                Button(
                    onClick = viewModel::searchServiceOrders,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                ) { Text("Buscar registro") }
            }
        }

        if (!state.serviceOptions.siteLocked && state.serviceOptions.sedes.size > 1) {
            StringSelector(
                label = "Sede",
                selected = state.serviceSite,
                values = state.serviceOptions.sedes,
                onSelected = viewModel::setServiceSite,
                modifier = Modifier.padding(horizontal = 16.dp),
            )
        }

        LazyRow(
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            item {
                FilterChip(
                    selected = state.serviceStatus.isBlank(),
                    onClick = { viewModel.setServiceStatus("") },
                    label = { Text("Todos") },
                )
            }
            items(state.serviceOptions.estados) { option ->
                FilterChip(
                    selected = state.serviceStatus == option.value,
                    onClick = { viewModel.setServiceStatus(option.value) },
                    label = { Text(option.label) },
                )
            }
        }

        Surface(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
            shape = RoundedCornerShape(14.dp),
            color = MaterialTheme.colorScheme.primaryContainer,
        ) {
            Button(
                onClick = { creating = true },
                modifier = Modifier.fillMaxWidth().padding(5.dp),
                shape = RoundedCornerShape(10.dp),
            ) {
                Text("+  Registrar celular", fontWeight = FontWeight.Bold)
            }
        }
        Spacer(Modifier.height(8.dp))

        when {
            state.serviceOrdersLoading && state.serviceOrders.isEmpty() ->
                androidx.compose.foundation.layout.Box(
                    Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center,
                ) { CircularProgressIndicator() }
            state.serviceOrdersError != null && state.serviceOrders.isEmpty() ->
                ServiceError(state.serviceOrdersError) { viewModel.loadServiceOrders() }
            state.serviceOrders.isEmpty() ->
                androidx.compose.foundation.layout.Box(
                    Modifier.fillMaxSize().padding(24.dp),
                    contentAlignment = Alignment.Center,
                ) { Text("No hay celulares registrados") }
            else -> LazyColumn(
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 6.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                items(state.serviceOrders, key = ServiceOrderDto::id) { order ->
                    ServiceOrderRow(order) { viewModel.openServiceOrder(order) }
                }
                item {
                    if (state.serviceOrdersPage < state.serviceOrdersLastPage) {
                        OutlinedButton(
                            onClick = viewModel::loadMoreServiceOrders,
                            enabled = !state.serviceOrdersLoading,
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Text(if (state.serviceOrdersLoading) "Cargando…" else "Cargar más")
                        }
                    }
                }
            }
        }
    }

    if (creating) {
        CreatePhoneOrderDialog(
            state = state,
            onDismiss = { if (!state.submitting) creating = false },
            onSubmit = { request ->
                viewModel.createServiceOrder(request) { creating = false }
            },
        )
    }

    state.selectedServiceOrder?.let { order ->
        ServiceOrderDetailDialog(
            order = order,
            submitting = state.submitting,
            pdfLoading = state.receptionPdfLoading,
            onStatus = { status, comment ->
                viewModel.changeServiceOrderStatus(order, status, comment)
            },
            onPdf = { viewModel.openReceptionPdf(order) },
            onDismiss = viewModel::closeServiceOrder,
        )
    }

    state.receptionPdf?.let { pdf ->
        ReceptionPdfDialog(pdf, viewModel::closeReceptionPdf)
    }
}

@Composable
private fun ServiceOrderRow(order: ServiceOrderDto, onOpen: () -> Unit) {
    val (statusColor, statusBackground) = serviceStatusColors(order.estado)
    Surface(
        shape = RoundedCornerShape(14.dp),
        color = MaterialTheme.colorScheme.surface,
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
        shadowElevation = 1.dp,
        onClick = onOpen,
    ) {
        Column(Modifier.fillMaxWidth().padding(15.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Row(
                Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    order.codigo,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                    style = MaterialTheme.typography.titleMedium,
                )
                Surface(color = statusBackground, shape = RoundedCornerShape(20.dp)) {
                    Text(
                        order.statusLabel.ifBlank { order.estado },
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                        style = MaterialTheme.typography.labelMedium,
                        fontWeight = FontWeight.SemiBold,
                        color = statusColor,
                    )
                }
            }
            Text(
                listOfNotNull(order.marca, order.modelo).joinToString(" ").ifBlank { "Celular" },
                fontWeight = FontWeight.SemiBold,
            )
            Text("IMEI  ${order.imei}", style = MaterialTheme.typography.bodySmall, color = NexoMuted)
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                InfoPill(order.managementTypeLabel)
                InfoPill(order.sede)
                InfoPill(order.priorityLabel)
            }
            if (order.clientName.isNotBlank()) {
                Text("Cliente: ${order.clientName}", style = MaterialTheme.typography.bodySmall, color = NexoMuted)
            }
            Text(order.falla, maxLines = 2, style = MaterialTheme.typography.bodySmall, color = NexoMuted)
        }
    }
}

@Composable
private fun InfoPill(text: String) {
    if (text.isBlank()) return
    Surface(color = MaterialTheme.colorScheme.surfaceVariant, shape = RoundedCornerShape(6.dp)) {
        Text(
            text,
            modifier = Modifier.padding(horizontal = 7.dp, vertical = 3.dp),
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun CreatePhoneOrderDialog(
    state: AppUiState,
    onDismiss: () -> Unit,
    onSubmit: (CreateServiceOrderRequest) -> Unit,
) {
    val siteChoices = state.serviceOptions.sedes.ifEmpty {
        listOf("DORAL", "CENTRO", "ZAMORA", "SAMBIL", "VIRTUDES")
    }
    val managementChoices = state.serviceOptions.managementTypes.ifEmpty {
        listOf(
            ChoiceDto("ST", "Servicio técnico"),
            ChoiceDto("GARANTIA", "Garantía"),
            ChoiceDto("REPARACION_INTERNA", "Reparación interna"),
        )
    }
    val warrantyChoices = state.serviceOptions.warrantyRanges.ifEmpty {
        listOf(
            ChoiceDto("dentro", "Dentro del rango"),
            ChoiceDto("fuera", "Fuera del rango"),
        )
    }
    val priorityChoices = state.serviceOptions.priorities.ifEmpty {
        listOf(
            ChoiceDto("baja", "Baja"),
            ChoiceDto("normal", "Normal"),
            ChoiceDto("alta", "Alta"),
            ChoiceDto("urgente", "Urgente"),
        )
    }
    var site by remember(siteChoices, state.serviceSite) {
        mutableStateOf(state.serviceSite.ifBlank { siteChoices.first() })
    }
    var managementType by remember { mutableStateOf("ST") }
    var warrantyRange by remember { mutableStateOf("") }
    var clientName by remember { mutableStateOf("") }
    var clientPhone by remember { mutableStateOf("") }
    var clientId by remember { mutableStateOf("") }
    var imei by remember { mutableStateOf("") }
    var imeiNotApplicable by remember { mutableStateOf(false) }
    var serial by remember { mutableStateOf("") }
    var brand by remember { mutableStateOf("") }
    var model by remember { mutableStateOf("") }
    var color by remember { mutableStateOf("") }
    var storage by remember { mutableStateOf("") }
    var deviceValue by remember { mutableStateOf("") }
    var failure by remember { mutableStateOf("") }
    var accessories by remember { mutableStateOf("") }
    var priority by remember { mutableStateOf("normal") }
    var promisedDate by remember { mutableStateOf("") }
    var notes by remember { mutableStateOf("") }
    var useExisting by remember { mutableStateOf(false) }
    val inspection = remember { mutableStateMapOf<String, String>() }
    val signatureStrokes = remember { mutableStateListOf<List<Offset>>() }
    var signatureSize by remember { mutableStateOf(IntSize.Zero) }
    val needsClient = managementType == "ST" || (managementType == "GARANTIA" && warrantyRange == "fuera")

    AlertDialog(
        onDismissRequest = onDismiss,
        shape = RoundedCornerShape(20.dp),
        containerColor = MaterialTheme.colorScheme.surface,
        title = {
            Column {
                Text("Registrar celular", style = MaterialTheme.typography.titleLarge)
                Text(
                    "Completa la recepción del equipo",
                    style = MaterialTheme.typography.bodySmall,
                    color = NexoMuted,
                )
            }
        },
        text = {
            LazyColumn(
                modifier = Modifier.fillMaxWidth().heightIn(max = 620.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                item {
                    ChoiceSelector(
                        "Tipo de gestión",
                        managementType,
                        managementChoices,
                    ) { managementType = it }
                }
                if (managementType == "GARANTIA") {
                    item {
                        ChoiceSelector(
                            "Rango de garantía",
                            warrantyRange,
                            warrantyChoices,
                        ) { warrantyRange = it }
                    }
                }
                if (!state.serviceOptions.siteLocked) {
                    item {
                        StringSelector("Sede", site, siteChoices, { site = it })
                    }
                }
                if (needsClient) {
                    item { PhoneField(clientName, { clientName = it }, "Cliente *") }
                    item { PhoneField(clientPhone, { clientPhone = it }, "Teléfono") }
                    item { PhoneField(clientId, { clientId = it }, "Cédula") }
                }
                item {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Checkbox(
                            checked = imeiNotApplicable,
                            onCheckedChange = {
                                imeiNotApplicable = it
                                if (it) imei = ""
                            },
                        )
                        Text("IMEI no aplica")
                    }
                }
                if (!imeiNotApplicable) {
                    item {
                        PhoneField(
                            imei,
                            { imei = it.filter(Char::isDigit).take(32) },
                            "IMEI *",
                            KeyboardType.Number,
                        )
                    }
                } else {
                    item { PhoneField(serial, { serial = it }, "Serial *") }
                }
                item { PhoneField(brand, { brand = it }, "Marca *") }
                item { PhoneField(model, { model = it }, "Modelo *") }
                item { PhoneField(color, { color = it }, "Color *") }
                item { PhoneField(storage, { storage = it }, "Almacenamiento * (ej. 128 GB)") }
                item {
                    PhoneField(
                        deviceValue,
                        { deviceValue = it },
                        "Valor del dispositivo",
                        KeyboardType.Decimal,
                    )
                }
                item { PhoneField(failure, { failure = it }, "Falla o motivo *", singleLine = false) }
                item { PhoneField(accessories, { accessories = it }, "Accesorios recibidos") }
                item {
                    ChoiceSelector("Prioridad", priority, priorityChoices) {
                        priority = it
                    }
                }
                if (needsClient) {
                    item { PhoneField(promisedDate, { promisedDate = it }, "Fecha prometida (AAAA-MM-DD)") }
                }
                item { PhoneField(notes, { notes = it }, "Observaciones", singleLine = false) }
                item {
                    Text("Inspección de recepción", fontWeight = FontWeight.SemiBold)
                }
                items(state.serviceOptions.checklist) { check ->
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(check.label, style = MaterialTheme.typography.bodySmall)
                        LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            items(listOf("ok" to "OK", "dano" to "Daño", "na" to "N/A")) { (value, label) ->
                                FilterChip(
                                    selected = inspection[check.value] == value,
                                    onClick = { inspection[check.value] = value },
                                    label = { Text(label) },
                                )
                            }
                        }
                    }
                }
                if (needsClient) {
                    item {
                        SignaturePad(
                            strokes = signatureStrokes,
                            onSizeChanged = { signatureSize = it },
                            onClear = { signatureStrokes.clear() },
                        )
                    }
                }
                if (!imeiNotApplicable) {
                    item {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Checkbox(checked = useExisting, onCheckedChange = { useExisting = it })
                            Text("Usar este IMEI si ya está registrado")
                        }
                    }
                }
            }
        },
        confirmButton = {
            Button(
                enabled = !state.submitting,
                onClick = {
                    onSubmit(
                        CreateServiceOrderRequest(
                            sede = site,
                            managementType = managementType,
                            warrantyRange = warrantyRange.ifBlank { null },
                            deviceValue = deviceValue.replace(',', '.').toDoubleOrNull(),
                            clientName = clientName.ifBlank { null },
                            clientPhone = clientPhone.ifBlank { null },
                            clientId = clientId.ifBlank { null },
                            imei = imei.ifBlank { null },
                            imeiNotApplicable = imeiNotApplicable,
                            serial = serial.ifBlank { null },
                            marca = brand,
                            modelo = model,
                            color = color,
                            almacenamiento = storage,
                            falla = failure,
                            accesorios = accessories.ifBlank { null },
                            prioridad = priority,
                            promisedDate = promisedDate.ifBlank { null },
                            observaciones = notes.ifBlank { null },
                            inspeccion = inspection.toMap(),
                            clientSignature = signatureToDataUrl(signatureStrokes, signatureSize),
                            useExistingDevice = useExisting,
                        ),
                    )
                },
            ) {
                Text(if (state.submitting) "Guardando…" else "Registrar")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss, enabled = !state.submitting) { Text("Cancelar") }
        },
    )
}

@Composable
private fun ServiceOrderDetailDialog(
    order: ServiceOrderDto,
    submitting: Boolean,
    pdfLoading: Boolean,
    onStatus: (String, String) -> Unit,
    onPdf: () -> Unit,
    onDismiss: () -> Unit,
) {
    var status by remember(order.id, order.estado) { mutableStateOf("") }
    var comment by remember(order.id, order.estado) { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        shape = RoundedCornerShape(20.dp),
        containerColor = MaterialTheme.colorScheme.surface,
        title = { Text("${order.codigo} · ${order.statusLabel}") },
        text = {
            LazyColumn(
                modifier = Modifier.fillMaxWidth().heightIn(max = 600.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                item {
                    Text(
                        listOfNotNull(order.marca, order.modelo, order.color).joinToString(" "),
                        fontWeight = FontWeight.SemiBold,
                    )
                    Text("IMEI: ${order.imei}")
                    Text("${order.managementTypeLabel} · ${order.sede}")
                    order.deviceValue?.let { Text("Valor: ${"%.2f".format(it)}") }
                    if (order.clientName.isNotBlank()) Text("Cliente: ${order.clientName}")
                    Text("Falla: ${order.falla}")
                    order.diagnostico?.takeIf(String::isNotBlank)?.let { Text("Diagnóstico: $it") }
                }
                item {
                    OutlinedButton(
                        onClick = onPdf,
                        enabled = !pdfLoading,
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        if (pdfLoading) {
                            CircularProgressIndicator(
                                modifier = Modifier.height(18.dp),
                                strokeWidth = 2.dp,
                            )
                        } else {
                            Text("Ver PDF de recepción")
                        }
                    }
                }
                if (order.allowedStatuses.isNotEmpty()) {
                    item { HorizontalDivider() }
                    item { Text("Cambiar estado", fontWeight = FontWeight.SemiBold) }
                    item {
                        ChoiceSelector("Nuevo estado", status, order.allowedStatuses) { status = it }
                    }
                    item {
                        PhoneField(
                            comment,
                            { comment = it },
                            "Motivo del cambio *",
                            singleLine = false,
                        )
                    }
                    item {
                        Button(
                            onClick = { if (status.isNotBlank()) onStatus(status, comment) },
                            enabled = !submitting && status.isNotBlank(),
                            modifier = Modifier.fillMaxWidth(),
                        ) { Text(if (submitting) "Actualizando…" else "Actualizar estado") }
                    }
                }
                item { HorizontalDivider() }
                item { Text("Bitácora", fontWeight = FontWeight.SemiBold) }
                if (order.eventos.isEmpty()) {
                    item { Text("Sin eventos registrados") }
                } else {
                    items(order.eventos, key = { it.id }) { event ->
                        Column {
                            Text(event.descripcion, style = MaterialTheme.typography.bodySmall)
                            Text(
                                listOfNotNull(event.usuario, event.fecha).joinToString(" · "),
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss, enabled = !submitting) { Text("Cerrar") }
        },
    )
}

@Composable
private fun PhoneField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    keyboardType: KeyboardType = KeyboardType.Text,
    singleLine: Boolean = true,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        label = { Text(label) },
        keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
        singleLine = singleLine,
        minLines = if (singleLine) 1 else 3,
        modifier = Modifier.fillMaxWidth(),
    )
}

@Composable
private fun ChoiceSelector(
    label: String,
    selected: String,
    choices: List<ChoiceDto>,
    onSelected: (String) -> Unit,
) {
    Column {
        Text(label, style = MaterialTheme.typography.labelMedium)
        LazyRow(horizontalArrangement = Arrangement.spacedBy(7.dp)) {
            items(choices, key = { it.value }) { choice ->
                FilterChip(
                    selected = selected == choice.value,
                    onClick = { onSelected(choice.value) },
                    label = { Text(choice.label) },
                )
            }
        }
    }
}

@Composable
private fun StringSelector(
    label: String,
    selected: String,
    values: List<String>,
    onSelected: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(modifier) {
        Text(label, style = MaterialTheme.typography.labelMedium)
        LazyRow(horizontalArrangement = Arrangement.spacedBy(7.dp)) {
            items(values, key = { it }) { value ->
                FilterChip(
                    selected = selected == value,
                    onClick = { onSelected(value) },
                    label = { Text(value) },
                )
            }
        }
    }
}

@Composable
private fun SignaturePad(
    strokes: MutableList<List<Offset>>,
    onSizeChanged: (IntSize) -> Unit,
    onClear: () -> Unit,
) {
    val currentStroke = remember { mutableStateListOf<Offset>() }
    val inkColor = MaterialTheme.colorScheme.onSurface

    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column {
                Text("Firma del cliente", fontWeight = FontWeight.SemiBold)
                Text("Firma dentro del recuadro", style = MaterialTheme.typography.bodySmall, color = NexoMuted)
            }
            TextButton(
                onClick = {
                    currentStroke.clear()
                    onClear()
                },
            ) { Text("Limpiar") }
        }
        Canvas(
            modifier = Modifier
                .fillMaxWidth()
                .height(170.dp)
                .background(Color.White, RoundedCornerShape(10.dp))
                .border(1.dp, MaterialTheme.colorScheme.outline, RoundedCornerShape(10.dp))
                .onSizeChanged(onSizeChanged)
                .pointerInput(Unit) {
                    detectDragGestures(
                        onDragStart = { point ->
                            currentStroke.clear()
                            currentStroke.add(point)
                        },
                        onDragEnd = {
                            if (currentStroke.isNotEmpty()) {
                                strokes.add(currentStroke.toList())
                                currentStroke.clear()
                            }
                        },
                        onDragCancel = { currentStroke.clear() },
                        onDrag = { change, _ ->
                            change.consume()
                            currentStroke.add(change.position)
                        },
                    )
                },
        ) {
            (strokes + listOf(currentStroke.toList())).forEach { points ->
                if (points.isEmpty()) return@forEach
                val path = Path().apply {
                    moveTo(points.first().x, points.first().y)
                    points.drop(1).forEach { lineTo(it.x, it.y) }
                }
                drawPath(
                    path = path,
                    color = inkColor,
                    style = Stroke(width = 4f, cap = StrokeCap.Round, join = StrokeJoin.Round),
                )
            }
        }
    }
}

private fun signatureToDataUrl(strokes: List<List<Offset>>, sourceSize: IntSize): String? {
    if (strokes.isEmpty() || sourceSize.width <= 0 || sourceSize.height <= 0) return null

    val width = 1000
    val height = 350
    val bitmap = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
    val canvas = AndroidCanvas(bitmap)
    canvas.drawColor(android.graphics.Color.WHITE)
    val paint = AndroidPaint().apply {
        color = android.graphics.Color.rgb(11, 31, 58)
        style = AndroidPaint.Style.STROKE
        strokeWidth = 6f
        strokeCap = AndroidPaint.Cap.ROUND
        strokeJoin = AndroidPaint.Join.ROUND
        isAntiAlias = true
    }
    val scaleX = width.toFloat() / sourceSize.width
    val scaleY = height.toFloat() / sourceSize.height
    strokes.forEach { points ->
        if (points.isEmpty()) return@forEach
        val path = AndroidPath().apply {
            moveTo(points.first().x * scaleX, points.first().y * scaleY)
            points.drop(1).forEach { lineTo(it.x * scaleX, it.y * scaleY) }
        }
        canvas.drawPath(path, paint)
    }

    val output = ByteArrayOutputStream()
    bitmap.compress(Bitmap.CompressFormat.PNG, 100, output)
    bitmap.recycle()

    return "data:image/png;base64," + Base64.encodeToString(output.toByteArray(), Base64.NO_WRAP)
}

@Composable
private fun ReceptionPdfDialog(pdf: ByteArray, onDismiss: () -> Unit) {
    val context = LocalContext.current
    val pages by produceState<List<Bitmap>?>(initialValue = null, pdf) {
        value = withContext(Dispatchers.IO) {
            val file = File.createTempFile("recepcion-", ".pdf", context.cacheDir)
            try {
                file.writeBytes(pdf)
                ParcelFileDescriptor.open(file, ParcelFileDescriptor.MODE_READ_ONLY).use { descriptor ->
                    PdfRenderer(descriptor).use { renderer ->
                        List(renderer.pageCount) { index ->
                            renderer.openPage(index).use { page ->
                                val bitmap = Bitmap.createBitmap(
                                    page.width * 2,
                                    page.height * 2,
                                    Bitmap.Config.ARGB_8888,
                                )
                                bitmap.eraseColor(android.graphics.Color.WHITE)
                                page.render(bitmap, null, null, PdfRenderer.Page.RENDER_MODE_FOR_DISPLAY)
                                bitmap
                            }
                        }
                    }
                }
            } finally {
                file.delete()
            }
        }
    }

    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false),
    ) {
        Surface(
            modifier = Modifier.fillMaxSize().padding(10.dp),
            shape = RoundedCornerShape(18.dp),
            color = MaterialTheme.colorScheme.background,
        ) {
            Column {
                Row(
                    modifier = Modifier.fillMaxWidth().background(Color.White).padding(12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text("PDF de recepción", style = MaterialTheme.typography.titleMedium)
                    TextButton(onClick = onDismiss) { Text("Cerrar") }
                }
                if (pages == null) {
                    androidx.compose.foundation.layout.Box(
                        Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center,
                    ) { CircularProgressIndicator() }
                } else {
                    LazyColumn(
                        contentPadding = PaddingValues(10.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        items(pages.orEmpty()) { page ->
                            Surface(
                                color = Color.White,
                                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
                                shadowElevation = 2.dp,
                            ) {
                                Image(
                                    bitmap = page.asImageBitmap(),
                                    contentDescription = "Página del PDF",
                                    modifier = Modifier.fillMaxWidth(),
                                    contentScale = ContentScale.FillWidth,
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ServiceError(message: String, retry: () -> Unit) {
    Column(
        modifier = Modifier.fillMaxSize().padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Text(message, color = MaterialTheme.colorScheme.error)
        Spacer(Modifier.height(12.dp))
        Button(onClick = retry) { Text("Reintentar") }
    }
}

private fun serviceStatusColors(status: String): Pair<Color, Color> = when (status.lowercase()) {
    "listo", "entregado" -> NexoSuccess to Color(0xFFE8F8F2)
    "en_proceso" -> Color(0xFF1768C4) to Color(0xFFE8F2FF)
    "ubicando_repuesto" -> NexoWarning to Color(0xFFFFF5DC)
    "cancelado" -> NexoDanger to Color(0xFFFFE9EC)
    else -> NexoMuted to Color(0xFFEEF2F7)
}
