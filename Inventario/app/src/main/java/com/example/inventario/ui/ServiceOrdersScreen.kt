package com.example.inventario.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
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
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
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
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import com.example.inventario.data.ChoiceDto
import com.example.inventario.data.CreateServiceOrderRequest
import com.example.inventario.data.ServiceOrderDto
import com.example.inventario.ui.theme.NexoDanger
import com.example.inventario.ui.theme.NexoMuted
import com.example.inventario.ui.theme.NexoSuccess
import com.example.inventario.ui.theme.NexoWarning

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
            onStatus = { status, comment ->
                viewModel.changeServiceOrderStatus(order, status, comment)
            },
            onDismiss = viewModel::closeServiceOrder,
        )
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
    var site by remember { mutableStateOf(state.serviceSite) }
    var managementType by remember { mutableStateOf("ST") }
    var warrantyRange by remember { mutableStateOf("") }
    var clientName by remember { mutableStateOf("") }
    var clientPhone by remember { mutableStateOf("") }
    var clientId by remember { mutableStateOf("") }
    var imei by remember { mutableStateOf("") }
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
    val needsClient = managementType == "ST" || (managementType == "GARANTIA" && warrantyRange == "fuera")

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Registrar celular") },
        text = {
            LazyColumn(
                modifier = Modifier.fillMaxWidth().heightIn(max = 620.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                item {
                    ChoiceSelector(
                        "Tipo de gestión",
                        managementType,
                        state.serviceOptions.managementTypes,
                    ) { managementType = it }
                }
                if (managementType == "GARANTIA") {
                    item {
                        ChoiceSelector(
                            "Rango de garantía",
                            warrantyRange,
                            state.serviceOptions.warrantyRanges,
                        ) { warrantyRange = it }
                    }
                }
                if (!state.serviceOptions.siteLocked) {
                    item {
                        StringSelector("Sede", site, state.serviceOptions.sedes, { site = it })
                    }
                }
                if (needsClient) {
                    item { PhoneField(clientName, { clientName = it }, "Cliente *") }
                    item { PhoneField(clientPhone, { clientPhone = it }, "Teléfono") }
                    item { PhoneField(clientId, { clientId = it }, "Cédula") }
                }
                item {
                    PhoneField(
                        imei,
                        { imei = it.filter(Char::isDigit).take(32) },
                        "IMEI *",
                        KeyboardType.Number,
                    )
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
                    ChoiceSelector("Prioridad", priority, state.serviceOptions.priorities) {
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
                item {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Checkbox(checked = useExisting, onCheckedChange = { useExisting = it })
                        Text("Usar este IMEI si ya está registrado")
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
                            imei = imei,
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
    onStatus: (String, String) -> Unit,
    onDismiss: () -> Unit,
) {
    var status by remember(order.id, order.estado) { mutableStateOf("") }
    var comment by remember(order.id, order.estado) { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
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
    var expanded by remember { mutableStateOf(false) }
    val selectedLabel = choices.firstOrNull { it.value == selected }?.label ?: "Seleccione…"
    Column {
        Text(label, style = MaterialTheme.typography.labelMedium)
        OutlinedButton(onClick = { expanded = true }, modifier = Modifier.fillMaxWidth()) {
            Text(selectedLabel)
        }
        DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            choices.forEach { choice ->
                DropdownMenuItem(
                    text = { Text(choice.label) },
                    onClick = {
                        onSelected(choice.value)
                        expanded = false
                    },
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
    var expanded by remember { mutableStateOf(false) }
    Column(modifier) {
        Text(label, style = MaterialTheme.typography.labelMedium)
        OutlinedButton(onClick = { expanded = true }, modifier = Modifier.fillMaxWidth()) {
            Text(selected.ifBlank { "Seleccione…" })
        }
        DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            values.forEach { value ->
                DropdownMenuItem(
                    text = { Text(value) },
                    onClick = {
                        onSelected(value)
                        expanded = false
                    },
                )
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
