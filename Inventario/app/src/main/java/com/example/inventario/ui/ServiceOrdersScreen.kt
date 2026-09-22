package com.example.inventario.ui

import android.content.Intent
import android.graphics.Bitmap
import android.graphics.Canvas as AndroidCanvas
import android.graphics.Paint as AndroidPaint
import android.graphics.Path as AndroidPath
import android.graphics.pdf.PdfRenderer
import android.net.Uri
import android.os.ParcelFileDescriptor
import android.util.Base64
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.core.content.FileProvider
import android.widget.Toast
import com.example.inventario.data.ChoiceDto
import com.example.inventario.data.CreateServiceOrderRequest
import com.example.inventario.data.ServiceOrderDto
import com.example.inventario.data.TechnicianDto
import com.example.inventario.ui.theme.NexoDanger
import coil.compose.AsyncImage
import coil.request.ImageRequest
import com.example.inventario.ui.theme.NexoBorder
import com.example.inventario.ui.theme.NexoMuted
import com.example.inventario.ui.theme.NexoSuccess
import com.example.inventario.ui.theme.NexoWarning
import com.google.mlkit.vision.codescanner.GmsBarcodeScannerOptions
import com.google.mlkit.vision.codescanner.GmsBarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import java.io.ByteArrayOutputStream
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone
import java.io.File
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

@Composable
fun ServiceOrdersScreen(state: AppUiState, viewModel: AppViewModel) {
    Column(
        Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background),
    ) {
        Column(Modifier.padding(start = 18.dp, end = 18.dp, top = 14.dp, bottom = 10.dp)) {
            Text(
                "Consultar equipos",
                style = MaterialTheme.typography.titleLarge,
                color = MaterialTheme.colorScheme.onBackground,
            )
            Text(
                "Busca por orden, cliente o IMEI y revisa el seguimiento.",
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
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 6.dp),
            )
        }

        LazyRow(
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
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

        when {
            state.serviceOrdersLoading && state.serviceOrders.isEmpty() ->
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
            state.serviceOrdersError != null && state.serviceOrders.isEmpty() ->
                ServiceError(state.serviceOrdersError) { viewModel.loadServiceOrders() }
            state.serviceOrders.isEmpty() ->
                Box(
                    Modifier.fillMaxSize().padding(24.dp),
                    contentAlignment = Alignment.Center,
                ) { Text("No hay equipos registrados") }
            else -> LazyColumn(
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 6.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
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

    state.selectedServiceOrder?.let { order ->
        val context = LocalContext.current
        ServiceOrderDetailScreen(
            order = order,
            submitting = state.submitting,
            pdfLoading = state.receptionPdfLoading,
            onStatus = { status, comment ->
                viewModel.changeServiceOrderStatus(order, status, comment)
            },
            onPdf = { viewModel.openReceptionPdf(order) },
            onUploadEvidence = { images, video, replace ->
                viewModel.uploadServiceOrderEvidence(
                    order = order,
                    imageUris = images,
                    videoUri = video,
                    contentResolver = context.contentResolver,
                    replaceImages = replace,
                )
            },
            onDismiss = viewModel::closeServiceOrder,
        )
    }

    state.receptionPdf?.let { pdf ->
        ReceptionPdfDialog(
            pdf = pdf,
            fileName = "recepcion-${state.selectedServiceOrder?.codigo ?: "orden"}.pdf",
            onDismiss = viewModel::closeReceptionPdf,
        )
    }
}

@Composable
private fun ServiceOrderRow(order: ServiceOrderDto, onOpen: () -> Unit) {
    val (statusColor, statusBackground) = serviceStatusColors(order.estado)
    Surface(
        shape = RoundedCornerShape(16.dp),
        color = MaterialTheme.colorScheme.surface,
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.7f)),
        shadowElevation = 1.dp,
        onClick = onOpen,
    ) {
        Column(
            Modifier.fillMaxWidth().padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(7.dp),
        ) {
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
                listOfNotNull(order.marca, order.modelo).joinToString(" ").ifBlank { "Equipo" },
                fontWeight = FontWeight.SemiBold,
            )
            Text(
                if (order.imei.isNotBlank()) "IMEI  ${order.imei}"
                else "Serial  ${order.serial.orEmpty().ifBlank { "—" }}",
                style = MaterialTheme.typography.bodySmall,
                color = NexoMuted,
            )
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                InfoPill(order.deviceTypeLabel.ifBlank { "Celular" })
                InfoPill(order.managementTypeLabel)
                InfoPill(order.sede)
                InfoPill(order.priorityLabel)
            }
            if (order.clientName.isNotBlank()) {
                Text(
                    "Cliente: ${order.clientName}",
                    style = MaterialTheme.typography.bodySmall,
                    color = NexoMuted,
                )
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
fun RegisterServiceOrderScreen(
    state: AppUiState,
    viewModel: AppViewModel,
) {
    val context = LocalContext.current
    var formKey by remember { mutableStateOf(0) }
    key(formKey) {
        CreatePhoneOrderForm(
            state = state,
            onSubmit = { request, images, video ->
                viewModel.createServiceOrder(
                    request = request,
                    imageUris = images,
                    videoUri = video,
                    contentResolver = context.contentResolver,
                ) {
                    formKey += 1
                }
            },
        )
    }
}

@Composable
private fun CreatePhoneOrderForm(
    state: AppUiState,
    onSubmit: (CreateServiceOrderRequest, List<Uri>, Uri?) -> Unit,
) {
    val context = LocalContext.current
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
    val deviceChoices = state.serviceOptions.deviceTypes.ifEmpty {
        listOf(
            ChoiceDto("celular", "Celular"),
            ChoiceDto("audifonos", "Audífonos"),
            ChoiceDto("impresora", "Impresora"),
            ChoiceDto("camara", "Cámara"),
            ChoiceDto("corneta", "Corneta / Altavoz"),
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
    var deviceType by remember { mutableStateOf("celular") }
    var sendToOtherSite by remember { mutableStateOf(false) }
    var destinationTechnicianId by remember { mutableStateOf<Long?>(null) }
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
    val evidenceImages = remember { mutableStateListOf<Uri>() }
    var evidenceVideo by remember { mutableStateOf<Uri?>(null) }
    val pickImages = rememberLauncherForActivityResult(
        ActivityResultContracts.PickMultipleVisualMedia(3),
    ) { uris ->
        evidenceImages.clear()
        evidenceImages.addAll(uris.take(3))
    }
    val pickVideo = rememberLauncherForActivityResult(
        ActivityResultContracts.PickVisualMedia(),
    ) { uri -> evidenceVideo = uri }
    fun scanInto(onResult: (String) -> Unit) {
        val options = GmsBarcodeScannerOptions.Builder()
            .setBarcodeFormats(
                Barcode.FORMAT_QR_CODE,
                Barcode.FORMAT_CODE_128,
                Barcode.FORMAT_CODE_39,
                Barcode.FORMAT_EAN_13,
                Barcode.FORMAT_EAN_8,
                Barcode.FORMAT_UPC_A,
                Barcode.FORMAT_DATA_MATRIX,
                Barcode.FORMAT_PDF417,
            )
            .enableAutoZoom()
            .build()
        GmsBarcodeScanning.getClient(context, options)
            .startScan()
            .addOnSuccessListener { barcode ->
                val value = barcode.rawValue?.trim().orEmpty()
                if (value.isNotBlank()) onResult(value)
            }
            .addOnFailureListener {
                Toast.makeText(
                    context,
                    "No se pudo abrir el escáner. Escribe el código a mano.",
                    Toast.LENGTH_LONG,
                ).show()
            }
            .addOnCanceledListener { /* usuario canceló */ }
    }
    val needsClient = managementType == "ST" || (managementType == "GARANTIA" && warrantyRange == "fuera")
    val isPhone = deviceType == "celular"
    val checklist = state.serviceOptions.checklists[deviceType]
        ?: state.serviceOptions.checklist
    val technicians = state.serviceOptions.tecnicos

    Column(
        Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background),
    ) {
        Column(
            Modifier
                .fillMaxWidth()
                .background(MaterialTheme.colorScheme.surface)
                .padding(horizontal = 14.dp, vertical = 8.dp),
        ) {
            Text("Registrar equipo", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
        }
        HorizontalDivider()
        Column(
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .imePadding()
                .verticalScroll(rememberScrollState())
                .padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {

            SectionCard("Tipo de gestión") {
                ChoiceSelector("", managementType, managementChoices) {
                    managementType = it
                }
            }
            SectionCard("Tipo de dispositivo") {
                ChoiceSelector("", deviceType, deviceChoices) {
                    deviceType = it
                    inspection.clear()
                    if (it != "celular") {
                        imeiNotApplicable = true
                        imei = ""
                    }
                }
            }
            if (managementType == "GARANTIA") {
                SectionCard("Rango de garantía") {
                    ChoiceSelector("", warrantyRange, warrantyChoices) {
                        warrantyRange = it
                    }
                }
            }
            if (state.serviceOptions.canTransfer) {
                SectionCard("¿Se envía a otra sede?") {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        FilterChip(
                            selected = !sendToOtherSite,
                            onClick = {
                                sendToOtherSite = false
                                destinationTechnicianId = null
                            },
                            label = { Text("No · trabajo local") },
                        )
                        FilterChip(
                            selected = sendToOtherSite,
                            onClick = { sendToOtherSite = true },
                            label = { Text("Sí · envío") },
                        )
                    }
                    if (sendToOtherSite) {
                        Spacer(Modifier.height(10.dp))
                        if (!state.serviceOptions.siteLocked) {
                            StringSelector("Sede de origen", site, siteChoices) {
                                site = it
                            }
                            Spacer(Modifier.height(8.dp))
                        }
                        TechnicianSelector(
                            selectedId = destinationTechnicianId,
                            technicians = technicians,
                            onSelected = { destinationTechnicianId = it },
                        )
                    } else if (!state.serviceOptions.siteLocked) {
                        Spacer(Modifier.height(10.dp))
                        StringSelector("Sede", site, siteChoices) { site = it }
                    }
                }
            } else if (!state.serviceOptions.siteLocked) {
                SectionCard("Sede") {
                    StringSelector("", site, siteChoices) { site = it }
                }
            }
            if (needsClient) {
                SectionCard("Cliente") {
                    PhoneField(clientName, { clientName = it }, "Cliente *")
                    Spacer(Modifier.height(8.dp))
                    PhoneField(clientPhone, { clientPhone = it }, "Teléfono")
                    Spacer(Modifier.height(8.dp))
                    PhoneField(clientId, { clientId = it }, "Cédula")
                }
            }
            SectionCard("Equipo") {
                if (isPhone) {
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
                    Spacer(Modifier.height(6.dp))
                    if (!imeiNotApplicable) {
                        PhoneField(
                            imei,
                            { imei = it.filter(Char::isDigit).take(32) },
                            "IMEI *",
                            KeyboardType.Number,
                        )
                        Spacer(Modifier.height(6.dp))
                        CompactOutlinedButton(onClick = {
                            scanInto { imei = it.filter(Char::isDigit).take(32) }
                        }) { Text("Escanear IMEI", style = MaterialTheme.typography.labelMedium) }
                    } else {
                        PhoneField(serial, { serial = it }, "Serial *")
                        Spacer(Modifier.height(6.dp))
                        CompactOutlinedButton(onClick = { scanInto { serial = it.take(64) } }) {
                            Text("Escanear serial", style = MaterialTheme.typography.labelMedium)
                        }
                    }
                } else {
                    PhoneField(serial, { serial = it }, "Serial *")
                    Spacer(Modifier.height(6.dp))
                    CompactOutlinedButton(onClick = { scanInto { serial = it.take(64) } }) {
                        Text("Escanear serial", style = MaterialTheme.typography.labelMedium)
                    }
                }
                Spacer(Modifier.height(8.dp))
                PhoneField(brand, { brand = it }, "Marca *")
                Spacer(Modifier.height(8.dp))
                PhoneField(model, { model = it }, "Modelo *")
                Spacer(Modifier.height(8.dp))
                PhoneField(color, { color = it }, if (isPhone) "Color *" else "Color")
                if (isPhone) {
                    Spacer(Modifier.height(8.dp))
                    PhoneField(storage, { storage = it }, "Almacenamiento * (ej. 128 GB)")
                }
                Spacer(Modifier.height(8.dp))
                PhoneField(
                    deviceValue,
                    { deviceValue = it },
                    "Valor del dispositivo",
                    KeyboardType.Decimal,
                )
                Spacer(Modifier.height(8.dp))
                PhoneField(failure, { failure = it }, "Falla o motivo *", singleLine = false)
                Spacer(Modifier.height(8.dp))
                PhoneField(accessories, { accessories = it }, "Accesorios recibidos")
            }
            SectionCard("Evidencias") {
                Text(
                    "Hasta 3 fotos y 1 video corto",
                    style = MaterialTheme.typography.bodySmall,
                    color = NexoMuted,
                )
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    CompactOutlinedButton(onClick = {
                        pickImages.launch(
                            PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly),
                        )
                    }) { Text("Fotos (${evidenceImages.size}/3)", style = MaterialTheme.typography.labelMedium) }
                    CompactOutlinedButton(onClick = {
                        pickVideo.launch(
                            PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.VideoOnly),
                        )
                    }) { Text(if (evidenceVideo != null) "Video listo" else "Video", style = MaterialTheme.typography.labelMedium) }
                }
                if (evidenceImages.isNotEmpty() || evidenceVideo != null) {
                    Spacer(Modifier.height(6.dp))
                    TextButton(onClick = {
                        evidenceImages.clear()
                        evidenceVideo = null
                    }) { Text("Quitar evidencias") }
                }
            }
            SectionCard("Prioridad y notas") {
                ChoiceSelector("Prioridad", priority, priorityChoices) {
                    priority = it
                }
                if (needsClient) {
                    Spacer(Modifier.height(8.dp))
                    PromisedDateField(
                        value = promisedDate,
                        onValueChange = { promisedDate = it },
                    )
                }
                Spacer(Modifier.height(8.dp))
                PhoneField(notes, { notes = it }, "Observaciones", singleLine = false)
            }
            SectionCard("Inspección de recepción") {
                checklist.forEach { check ->
                    Column(
                        Modifier.padding(bottom = 8.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp),
                    ) {
                        Text(check.label, style = MaterialTheme.typography.bodySmall)
                        Row(
                            Modifier.horizontalScroll(rememberScrollState()),
                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                        ) {
                            listOf("ok" to "OK", "dano" to "Daño", "na" to "N/A").forEach { (value, label) ->
                                FilterChip(
                                    selected = inspection[check.value] == value,
                                    onClick = { inspection[check.value] = value },
                                    label = { Text(label) },
                                )
                            }
                        }
                    }
                }
            }
            if (needsClient) {
                SectionCard("Firma") {
                    SignaturePad(
                        strokes = signatureStrokes,
                        onSizeChanged = { signatureSize = it },
                        onClear = { signatureStrokes.clear() },
                    )
                }
            }
            if (isPhone && !imeiNotApplicable) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Checkbox(checked = useExisting, onCheckedChange = { useExisting = it })
                    Text("Usar este IMEI si ya está registrado")
                }
            }
        }
        Surface(
            tonalElevation = 2.dp,
            shadowElevation = 4.dp,
        ) {
            Button(
                enabled = !state.submitting &&
                    (!sendToOtherSite || destinationTechnicianId != null),
                onClick = {
                    onSubmit(
                        CreateServiceOrderRequest(
                            sede = site,
                            managementType = managementType,
                            deviceType = deviceType,
                            sendToOtherSite = sendToOtherSite,
                            destinationTechnicianId = destinationTechnicianId,
                            warrantyRange = warrantyRange.ifBlank { null },
                            deviceValue = deviceValue.replace(',', '.').toDoubleOrNull(),
                            clientName = clientName.ifBlank { null },
                            clientPhone = clientPhone.ifBlank { null },
                            clientId = clientId.ifBlank { null },
                            imei = imei.ifBlank { null },
                            imeiNotApplicable = imeiNotApplicable || !isPhone,
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
                            clientSignature = signatureToDataUrl(
                                signatureStrokes,
                                signatureSize,
                            ),
                            useExistingDevice = useExisting,
                        ),
                        evidenceImages.toList(),
                        evidenceVideo,
                    )
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 8.dp)
                    .height(40.dp),
                shape = RoundedCornerShape(10.dp),
                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp),
            ) {
                Text(
                    if (state.submitting) "Guardando…" else "Registrar equipo",
                    style = MaterialTheme.typography.labelLarge,
                )
            }
        }
    }
}

@Composable
private fun ServiceOrderDetailScreen(
    order: ServiceOrderDto,
    submitting: Boolean,
    pdfLoading: Boolean,
    onStatus: (String, String) -> Unit,
    onPdf: () -> Unit,
    onUploadEvidence: (images: List<Uri>, video: Uri?, replaceImages: Boolean) -> Unit,
    onDismiss: () -> Unit,
) {
    var status by remember(order.id, order.estado) { mutableStateOf("") }
    var comment by remember(order.id, order.estado) { mutableStateOf("") }
    val statusChoices = remember(order.id, order.estado, order.allowedStatuses) {
        statusChoicesForOrder(order)
    }
    val context = LocalContext.current
    val pendingImages = remember(order.id) { mutableStateListOf<Uri>() }
    var pendingVideo by remember(order.id) { mutableStateOf<Uri?>(null) }
    var replaceImages by remember(order.id) { mutableStateOf(false) }
    val pickImages = rememberLauncherForActivityResult(
        ActivityResultContracts.PickMultipleVisualMedia(3),
    ) { uris ->
        pendingImages.clear()
        pendingImages.addAll(uris.take(3))
        if (uris.isNotEmpty()) replaceImages = order.evidencias.imagenes.isNotEmpty()
    }
    val pickVideo = rememberLauncherForActivityResult(
        ActivityResultContracts.PickVisualMedia(),
    ) { uri ->
        pendingVideo = uri
    }

    Dialog(
        onDismissRequest = { if (!submitting) onDismiss() },
        properties = DialogProperties(usePlatformDefaultWidth = false, dismissOnClickOutside = false),
    ) {
        Surface(
            modifier = Modifier.fillMaxSize(),
            color = MaterialTheme.colorScheme.background,
        ) {
            Column(Modifier.fillMaxSize()) {
                Row(
                    Modifier
                        .fillMaxWidth()
                        .background(MaterialTheme.colorScheme.surface)
                        .padding(horizontal = 12.dp, vertical = 10.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Column(Modifier.weight(1f)) {
                        Text(order.codigo, style = MaterialTheme.typography.titleLarge)
                        Text(
                            order.statusLabel.ifBlank { order.estado },
                            style = MaterialTheme.typography.bodySmall,
                            color = NexoMuted,
                        )
                    }
                    TextButton(onClick = onDismiss, enabled = !submitting) { Text("Cerrar") }
                }
                HorizontalDivider()
                LazyColumn(
                    modifier = Modifier.weight(1f).fillMaxWidth(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    item {
                        SectionCard("Equipo") {
                            Text(
                                listOfNotNull(order.marca, order.modelo, order.color)
                                    .joinToString(" "),
                                fontWeight = FontWeight.SemiBold,
                            )
                            Spacer(Modifier.height(6.dp))
                            DetailLine("Tipo", order.deviceTypeLabel.ifBlank { "Celular" })
                            DetailLine(
                                if (order.imei.isNotBlank()) "IMEI" else "Serial",
                                order.imei.ifBlank { order.serial.orEmpty().ifBlank { "—" } },
                            )
                            DetailLine("Gestión", "${order.managementTypeLabel} · ${order.sede}")
                            order.deviceValue?.let {
                                DetailLine("Valor", "%.2f".format(it))
                            }
                            if (order.clientName.isNotBlank()) {
                                DetailLine("Cliente", order.clientName)
                            }
                            order.clientPhone?.takeIf(String::isNotBlank)?.let {
                                DetailLine("Teléfono", it)
                            }
                            DetailLine("Falla", order.falla)
                            order.diagnostico?.takeIf(String::isNotBlank)?.let {
                                DetailLine("Diagnóstico", it)
                            }
                            order.observaciones?.takeIf(String::isNotBlank)?.let {
                                DetailLine("Observaciones", it)
                            }
                        }
                    }
                    item {
                        SectionCard("Evidencias") {
                            val imgs = order.evidencias.imagenes
                            val videoUrl = order.evidencias.video
                            if (imgs.isEmpty() && videoUrl.isNullOrBlank()) {
                                Text("Sin fotos ni video todavía", color = NexoMuted)
                            } else {
                                if (imgs.isNotEmpty()) {
                                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        items(imgs.size) { index ->
                                            val url = imgs[index]
                                            AsyncImage(
                                                model = ImageRequest.Builder(context)
                                                    .data(url)
                                                    .crossfade(true)
                                                    .build(),
                                                contentDescription = "Foto ${index + 1}",
                                                contentScale = ContentScale.Crop,
                                                modifier = Modifier
                                                    .size(112.dp)
                                                    .clip(RoundedCornerShape(10.dp))
                                                    .border(1.dp, NexoBorder, RoundedCornerShape(10.dp))
                                                    .clickable {
                                                        runCatching {
                                                            context.startActivity(
                                                                Intent(Intent.ACTION_VIEW, Uri.parse(url)),
                                                            )
                                                        }
                                                    },
                                            )
                                        }
                                    }
                                }
                                videoUrl?.takeIf(String::isNotBlank)?.let { url ->
                                    Spacer(Modifier.height(10.dp))
                                    OutlinedButton(
                                        onClick = {
                                            runCatching {
                                                context.startActivity(
                                                    Intent(Intent.ACTION_VIEW, Uri.parse(url)),
                                                )
                                            }
                                        },
                                        modifier = Modifier.fillMaxWidth(),
                                    ) {
                                        Text("Ver video")
                                    }
                                }
                            }
                            Spacer(Modifier.height(12.dp))
                            Text(
                                "Agregar o reemplazar (máx. 3 fotos + 1 video)",
                                style = MaterialTheme.typography.bodySmall,
                                color = NexoMuted,
                            )
                            Spacer(Modifier.height(8.dp))
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                CompactOutlinedButton(onClick = {
                                    pickImages.launch(
                                        PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly),
                                    )
                                }) {
                                    Text(
                                        "Fotos (${pendingImages.size}/3)",
                                        style = MaterialTheme.typography.labelMedium,
                                    )
                                }
                                CompactOutlinedButton(onClick = {
                                    pickVideo.launch(
                                        PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.VideoOnly),
                                    )
                                }) {
                                    Text(
                                        if (pendingVideo != null) "Video listo" else "Video",
                                        style = MaterialTheme.typography.labelMedium,
                                    )
                                }
                            }
                            if (pendingImages.isNotEmpty() && order.evidencias.imagenes.isNotEmpty()) {
                                Spacer(Modifier.height(6.dp))
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Checkbox(
                                        checked = replaceImages,
                                        onCheckedChange = { replaceImages = it },
                                    )
                                    Text(
                                        "Reemplazar fotos actuales",
                                        style = MaterialTheme.typography.bodySmall,
                                    )
                                }
                            }
                            if (pendingImages.isNotEmpty() || pendingVideo != null) {
                                Spacer(Modifier.height(8.dp))
                                Button(
                                    onClick = {
                                        onUploadEvidence(
                                            pendingImages.toList(),
                                            pendingVideo,
                                            replaceImages,
                                        )
                                        pendingImages.clear()
                                        pendingVideo = null
                                        replaceImages = false
                                    },
                                    enabled = !submitting,
                                    modifier = Modifier.fillMaxWidth(),
                                ) {
                                    Text(if (submitting) "Subiendo…" else "Guardar evidencias")
                                }
                            }
                        }
                    }
                    item {
                        Button(
                            onClick = onPdf,
                            enabled = !pdfLoading,
                            modifier = Modifier.fillMaxWidth().height(48.dp),
                            shape = RoundedCornerShape(12.dp),
                        ) {
                            if (pdfLoading) {
                                CircularProgressIndicator(
                                    modifier = Modifier.size(18.dp),
                                    strokeWidth = 2.dp,
                                    color = MaterialTheme.colorScheme.onPrimary,
                                )
                                Spacer(Modifier.width(10.dp))
                                Text("Cargando PDF…")
                            } else {
                                Text("Ver / compartir PDF de recepción")
                            }
                        }
                    }
                    if (statusChoices.isNotEmpty()) {
                        item {
                            SectionCard("Cambiar estado") {
                                ChoiceSelector("Nuevo estado", status, statusChoices) {
                                    status = it
                                }
                                Spacer(Modifier.height(8.dp))
                                PhoneField(
                                    comment,
                                    { comment = it },
                                    "Motivo del cambio *",
                                    singleLine = false,
                                )
                                Spacer(Modifier.height(10.dp))
                                Button(
                                    onClick = { if (status.isNotBlank()) onStatus(status, comment) },
                                    enabled = !submitting && status.isNotBlank(),
                                    modifier = Modifier.fillMaxWidth(),
                                ) {
                                    Text(if (submitting) "Actualizando…" else "Actualizar estado")
                                }
                            }
                        }
                    }
                    item {
                        SectionCard("Bitácora") {
                            if (order.eventos.isEmpty()) {
                                Text("Sin eventos registrados", color = NexoMuted)
                            } else {
                                order.eventos.forEachIndexed { index, event ->
                                    if (index > 0) {
                                        Spacer(Modifier.height(8.dp))
                                        HorizontalDivider()
                                        Spacer(Modifier.height(8.dp))
                                    }
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
                }
            }
        }
    }
}

@Composable
private fun SectionCard(title: String, content: @Composable () -> Unit) {
    Surface(
        shape = RoundedCornerShape(10.dp),
        color = MaterialTheme.colorScheme.surface,
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.65f)),
    ) {
        Column(
            Modifier.fillMaxWidth().padding(horizontal = 10.dp, vertical = 8.dp),
            verticalArrangement = Arrangement.spacedBy(2.dp),
        ) {
            Text(title, style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(2.dp))
            content()
        }
    }
}

@Composable
private fun DetailLine(label: String, value: String) {
    Text(
        "$label: $value",
        style = MaterialTheme.typography.bodyMedium,
        color = MaterialTheme.colorScheme.onSurface,
        modifier = Modifier.padding(vertical = 2.dp),
    )
}

@Composable
private fun TechnicianSelector(
    selectedId: Long?,
    technicians: List<TechnicianDto>,
    onSelected: (Long) -> Unit,
) {
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text("Enviar a *", style = MaterialTheme.typography.labelMedium)
        if (technicians.isEmpty()) {
            Text(
                "No hay otra persona activa en Servicio técnico.",
                style = MaterialTheme.typography.bodySmall,
                color = NexoMuted,
            )
        } else {
            Row(
                Modifier.horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(7.dp),
            ) {
                technicians.forEach { tech ->
                    FilterChip(
                        selected = selectedId == tech.id,
                        onClick = { onSelected(tech.id) },
                        label = { Text(tech.label.ifBlank { "${tech.nombre} · ${tech.sede}" }) },
                    )
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PromisedDateField(
    value: String,
    onValueChange: (String) -> Unit,
) {
    var showPicker by remember { mutableStateOf(false) }
    val dateFormat = remember {
        SimpleDateFormat("yyyy-MM-dd", Locale.US).apply {
            timeZone = TimeZone.getTimeZone("UTC")
        }
    }
    val initialMillis = remember(value) {
        runCatching { dateFormat.parse(value)?.time }.getOrNull()
    }
    val datePickerState = rememberDatePickerState(initialSelectedDateMillis = initialMillis)

    Box(Modifier.fillMaxWidth()) {
        OutlinedTextField(
            value = value,
            onValueChange = {},
            readOnly = true,
            enabled = false,
            label = { Text("Fecha prometida") },
            placeholder = { Text("Elegir en el calendario") },
            trailingIcon = {
                Text("Cal.", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
            },
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(10.dp),
            colors = OutlinedTextFieldDefaults.colors(
                disabledTextColor = MaterialTheme.colorScheme.onSurface,
                disabledBorderColor = MaterialTheme.colorScheme.outline,
                disabledLabelColor = MaterialTheme.colorScheme.onSurfaceVariant,
                disabledPlaceholderColor = MaterialTheme.colorScheme.onSurfaceVariant,
                disabledTrailingIconColor = MaterialTheme.colorScheme.primary,
                disabledContainerColor = Color.Transparent,
            ),
        )
        Box(
            Modifier
                .matchParentSize()
                .clickable { showPicker = true },
        )
    }

    if (showPicker) {
        DatePickerDialog(
            onDismissRequest = { showPicker = false },
            confirmButton = {
                TextButton(
                    onClick = {
                        datePickerState.selectedDateMillis?.let { millis ->
                            onValueChange(dateFormat.format(Date(millis)))
                        }
                        showPicker = false
                    },
                ) { Text("OK") }
            },
            dismissButton = {
                TextButton(onClick = { showPicker = false }) { Text("Cancelar") }
            },
        ) {
            DatePicker(state = datePickerState, showModeToggle = false)
        }
    }
}

@Composable
private fun CompactOutlinedButton(
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    content: @Composable RowScope.() -> Unit,
) {
    OutlinedButton(
        onClick = onClick,
        modifier = modifier.height(34.dp),
        contentPadding = PaddingValues(horizontal = 10.dp, vertical = 2.dp),
        shape = RoundedCornerShape(8.dp),
        content = content,
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
        shape = RoundedCornerShape(10.dp),
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
        if (label.isNotBlank()) {
            Text(label, style = MaterialTheme.typography.labelMedium)
            Spacer(Modifier.height(4.dp))
        }
        Row(
            Modifier.horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(7.dp),
        ) {
            choices.forEach { choice ->
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
    modifier: Modifier = Modifier,
    onSelected: (String) -> Unit,
) {
    Column(modifier) {
        if (label.isNotBlank()) {
            Text(label, style = MaterialTheme.typography.labelMedium)
            Spacer(Modifier.height(4.dp))
        }
        Row(
            Modifier.horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(7.dp),
        ) {
            values.forEach { value ->
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
                Text(
                    "Firma dentro del recuadro",
                    style = MaterialTheme.typography.bodySmall,
                    color = NexoMuted,
                )
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
private fun ReceptionPdfDialog(pdf: ByteArray, fileName: String, onDismiss: () -> Unit) {
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

    fun sharePdf() {
        val safeName = fileName.replace(Regex("[^A-Za-z0-9._-]"), "_")
        val file = File(context.cacheDir, safeName)
        file.writeBytes(pdf)
        val uri = FileProvider.getUriForFile(
            context,
            "${context.packageName}.fileprovider",
            file,
        )
        val intent = Intent(Intent.ACTION_SEND).apply {
            type = "application/pdf"
            putExtra(Intent.EXTRA_STREAM, uri)
            putExtra(Intent.EXTRA_SUBJECT, "PDF de recepción")
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        context.startActivity(Intent.createChooser(intent, "Compartir PDF de recepción"))
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
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(Color.White)
                        .padding(horizontal = 8.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(
                        "PDF de recepción",
                        style = MaterialTheme.typography.titleMedium,
                        modifier = Modifier.weight(1f),
                    )
                    TextButton(onClick = ::sharePdf) { Text("Compartir") }
                    TextButton(onClick = onDismiss) { Text("Cerrar") }
                }
                if (pages == null) {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator()
                    }
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
    "listo", "completado", "entregado" -> NexoSuccess to Color(0xFFE8F8F2)
    "en_proceso" -> Color(0xFF1768C4) to Color(0xFFE8F2FF)
    "ubicando_repuesto" -> NexoWarning to Color(0xFFFFF5DC)
    "cancelado" -> NexoDanger to Color(0xFFFFE9EC)
    else -> NexoMuted to Color(0xFFEEF2F7)
}

/** Asegura que "Completado" (listo) aparezca aunque el API aún no lo envíe. */
private fun statusChoicesForOrder(order: ServiceOrderDto): List<ChoiceDto> {
    val current = order.estado.lowercase()
    val canComplete = current in setOf("pendiente", "en_proceso", "ubicando_repuesto")
    val mapped = order.allowedStatuses.map { choice ->
        if (choice.value.equals("listo", ignoreCase = true)) {
            choice.copy(label = "Completado")
        } else {
            choice
        }
    }.toMutableList()
    if (canComplete && mapped.none { it.value.equals("listo", ignoreCase = true) }) {
        mapped.add(ChoiceDto(value = "listo", label = "Completado"))
    }
    return mapped
}
