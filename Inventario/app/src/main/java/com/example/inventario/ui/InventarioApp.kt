package com.example.inventario.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.example.inventario.data.InventarioItemDto
import com.example.inventario.data.MetricasDto
import com.example.inventario.data.RequisitionDto
import com.example.inventario.data.SedeDto

private const val LOGIN_ROUTE = "login"
private const val HOME_ROUTE = "home"

@Composable
fun InventarioApp(viewModel: AppViewModel) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val navController = rememberNavController()
    val snackbar = remember { SnackbarHostState() }

    if (state.checkingSession) {
        LoadingScreen()
        return
    }

    LaunchedEffect(state.user) {
        val target = if (state.user == null) LOGIN_ROUTE else HOME_ROUTE
        navController.navigate(target) {
            popUpTo(navController.graph.startDestinationId) { inclusive = true }
            launchSingleTop = true
        }
    }
    LaunchedEffect(state.message) {
        state.message?.let {
            snackbar.showSnackbar(it)
            viewModel.consumeMessage()
        }
    }

    Scaffold(snackbarHost = { SnackbarHost(snackbar) }) { padding ->
        NavHost(
            navController = navController,
            startDestination = if (state.user == null) LOGIN_ROUTE else HOME_ROUTE,
            modifier = Modifier.padding(padding),
        ) {
            composable(LOGIN_ROUTE) {
                LoginScreen(
                    loading = state.loginLoading,
                    error = state.loginError,
                    onLogin = viewModel::login,
                )
            }
            composable(HOME_ROUTE) {
                HomeScreen(state = state, viewModel = viewModel)
            }
        }
    }
}

@Composable
private fun LoadingScreen() {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        CircularProgressIndicator()
    }
}

@Composable
private fun LoginScreen(
    loading: Boolean,
    error: String?,
    onLogin: (String, String) -> Unit,
) {
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.primaryContainer)
            .padding(24.dp),
        contentAlignment = Alignment.Center,
    ) {
        Surface(
            modifier = Modifier.fillMaxWidth(),
            shape = MaterialTheme.shapes.large,
            tonalElevation = 4.dp,
        ) {
            Column(
                modifier = Modifier.padding(24.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                Text(
                    text = "Nexo Celulares",
                    style = MaterialTheme.typography.headlineMedium,
                    color = MaterialTheme.colorScheme.primary,
                    fontWeight = FontWeight.Bold,
                )
                Text("Registro y seguimiento de equipos", color = MaterialTheme.colorScheme.onSurfaceVariant)
                OutlinedTextField(
                    value = email,
                    onValueChange = { email = it },
                    label = { Text("Correo") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )
                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    label = { Text("Contraseña") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                    visualTransformation = PasswordVisualTransformation(),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )
                error?.let {
                    Text(it, color = MaterialTheme.colorScheme.error)
                }
                Button(
                    onClick = { onLogin(email, password) },
                    enabled = !loading,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    if (loading) {
                        CircularProgressIndicator(
                            modifier = Modifier.width(20.dp),
                            strokeWidth = 2.dp,
                        )
                    } else {
                        Text("Iniciar sesión")
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun HomeScreen(state: AppUiState, viewModel: AppViewModel) {
    var selectedTab by remember { mutableIntStateOf(0) }
    val tabs = listOf("Consultar", "Registrar")

    Scaffold(
        topBar = {
            TopAppBar(
                windowInsets = WindowInsets(0, 0, 0, 0),
                expandedHeight = 48.dp,
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surface,
                    titleContentColor = MaterialTheme.colorScheme.onSurface,
                ),
                title = {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        Text(
                            "Nexo PD",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.primary,
                        )
                        Text(
                            state.user?.displayName.orEmpty(),
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                },
                actions = {
                    TextButton(
                        onClick = viewModel::logout,
                        contentPadding = PaddingValues(horizontal = 8.dp, vertical = 4.dp),
                    ) { Text("Salir") }
                },
            )
        },
        bottomBar = {
            NavigationBar(
                modifier = Modifier.height(56.dp),
                windowInsets = WindowInsets(0, 0, 0, 0),
                tonalElevation = 2.dp,
            ) {
                tabs.forEachIndexed { index, label ->
                    NavigationBarItem(
                        selected = selectedTab == index,
                        onClick = {
                            selectedTab = index
                            if (index == 1) {
                                viewModel.closeServiceOrder()
                                viewModel.closeReceptionPdf()
                            }
                        },
                        icon = {
                            Text(
                                if (index == 0) "⌕" else "+",
                                style = MaterialTheme.typography.titleSmall,
                                fontWeight = FontWeight.Bold,
                            )
                        },
                        label = {
                            Text(label, style = MaterialTheme.typography.labelSmall)
                        },
                        alwaysShowLabel = true,
                    )
                }
            }
        },
    ) { padding ->
        Box(Modifier.padding(padding).fillMaxSize()) {
            when (selectedTab) {
                0 -> ServiceOrdersScreen(state, viewModel)
                else -> RegisterServiceOrderScreen(state, viewModel)
            }
        }
    }
}

@Composable
private fun SiteSelector(
    sites: List<SedeDto>,
    selected: SedeDto?,
    onSelected: (SedeDto) -> Unit,
    modifier: Modifier = Modifier,
    label: String,
    enabled: Boolean = true,
) {
    var expanded by remember { mutableStateOf(false) }
    Box(modifier) {
        OutlinedButton(
            onClick = { expanded = true },
            enabled = enabled && sites.isNotEmpty(),
            modifier = Modifier.fillMaxWidth(),
        ) {
            Text("$label: ${selected?.displayName ?: "Sin sedes"}")
        }
        DropdownMenu(
            expanded = expanded,
            onDismissRequest = { expanded = false },
        ) {
            sites.forEach { site ->
                DropdownMenuItem(
                    text = { Text(site.displayName) },
                    onClick = {
                        onSelected(site)
                        expanded = false
                    },
                )
            }
        }
    }
}

@Composable
private fun InventoryScreen(state: AppUiState, viewModel: AppViewModel) {
    var selectedItem by remember { mutableStateOf<InventarioItemDto?>(null) }

    Column(Modifier.fillMaxSize()) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            OutlinedTextField(
                value = state.search,
                onValueChange = viewModel::setSearch,
                label = { Text("Buscar producto o código") },
                singleLine = true,
                modifier = Modifier.weight(1f),
            )
            Spacer(Modifier.width(8.dp))
            Button(onClick = viewModel::searchInventory) { Text("Buscar") }
        }
        OutlinedTextField(
            value = state.category,
            onValueChange = viewModel::setCategory,
            label = { Text("Categoría (opcional)") },
            singleLine = true,
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp),
        )
        Spacer(Modifier.height(8.dp))

        when {
            state.inventoryLoading && state.inventory.isEmpty() -> LoadingScreen()
            state.inventoryError != null && state.inventory.isEmpty() ->
                ErrorState(state.inventoryError, viewModel::retryInventory)
            state.inventory.isEmpty() -> EmptyState("No hay productos para mostrar")
            else -> LazyColumn(
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                items(state.inventory, key = { it.codigo }) { item ->
                    InventoryRow(item = item, onRequest = { selectedItem = item })
                }
                item {
                    if (state.inventoryPage < state.inventoryLastPage) {
                        OutlinedButton(
                            onClick = viewModel::loadMoreInventory,
                            enabled = !state.inventoryLoading,
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Text(if (state.inventoryLoading) "Cargando…" else "Cargar más")
                        }
                    }
                }
            }
        }
    }

    selectedItem?.let { item ->
        RequisitionDialog(
            item = item,
            sites = state.originSites,
            activeSite = state.activeSite,
            metrics = state.metrics,
            metricsLoading = state.metricsLoading,
            submitting = state.submitting,
            onMetrics = { origin, quantity -> viewModel.loadMetrics(item, origin, quantity) },
            onSubmit = { origin, quantity ->
                viewModel.createRequisition(item, origin, quantity) {
                    selectedItem = null
                    viewModel.clearMetrics()
                }
            },
            onDismiss = {
                selectedItem = null
                viewModel.clearMetrics()
            },
        )
    }
}

@Composable
private fun InventoryRow(item: InventarioItemDto, onRequest: () -> Unit) {
    Surface(shape = MaterialTheme.shapes.medium, tonalElevation = 2.dp) {
        Column(Modifier.padding(16.dp)) {
            Text(item.displayName, fontWeight = FontWeight.SemiBold)
            Text(
                "${item.codigo} · ${item.categoria.ifBlank { "Sin categoría" }}",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("Disponible: ${formatNumber(item.available)} ${item.unidad}")
                Button(onClick = onRequest) { Text("Solicitar") }
            }
        }
    }
}

@Composable
private fun RequisitionDialog(
    item: InventarioItemDto,
    sites: List<SedeDto>,
    activeSite: SedeDto?,
    metrics: MetricasDto?,
    metricsLoading: Boolean,
    submitting: Boolean,
    onMetrics: (SedeDto?, Double?) -> Unit,
    onSubmit: (SedeDto?, Double?) -> Unit,
    onDismiss: () -> Unit,
) {
    var origin by remember(item.codigo) { mutableStateOf<SedeDto?>(null) }
    var quantityText by remember(item.codigo) { mutableStateOf("") }
    val quantity = quantityText.replace(',', '.').toDoubleOrNull()

    AlertDialog(
        onDismissRequest = { if (!submitting) onDismiss() },
        title = { Text("Nueva requisición") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(item.displayName, fontWeight = FontWeight.SemiBold)
                Text("Destino: ${activeSite?.displayName ?: "Selecciona una sede activa"}")
                SiteSelector(
                    sites = sites.filter { it.apiValue != activeSite?.apiValue },
                    selected = origin,
                    onSelected = { origin = it },
                    label = "Sede origen",
                )
                OutlinedTextField(
                    value = quantityText,
                    onValueChange = {
                        quantityText = it
                    },
                    label = { Text("Cantidad") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )
                OutlinedButton(
                    onClick = { onMetrics(origin, quantity) },
                    enabled = !metricsLoading,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text(if (metricsLoading) "Consultando…" else "Consultar métricas")
                }
                metrics?.let { MetricsSummary(it) }
            }
        },
        confirmButton = {
            Button(
                onClick = { onSubmit(origin, quantity) },
                enabled = !submitting,
            ) {
                Text(if (submitting) "Enviando…" else "Crear")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss, enabled = !submitting) { Text("Cancelar") }
        },
    )
}

@Composable
private fun MetricsSummary(metrics: MetricasDto) {
    Surface(
        color = MaterialTheme.colorScheme.secondaryContainer,
        shape = MaterialTheme.shapes.small,
    ) {
        Column(Modifier.padding(12.dp)) {
            metrics.available?.let { Text("Disponible en origen: ${formatNumber(it)}") }
            metrics.demanda?.let { Text("Demanda proyectada: ${formatNumber(it)}") }
            metrics.excedente?.let { Text("Excedente disponible: ${formatNumber(it)}") }
            metrics.promedio?.let { Text("Consumo promedio: ${formatNumber(it)}") }
            metrics.sugerido?.let { Text("Cantidad sugerida: ${formatNumber(it)}") }
            if (metrics.displayMessage.isNotBlank()) Text(metrics.displayMessage)
        }
    }
}

@Composable
private fun RequisitionsScreen(state: AppUiState, viewModel: AppViewModel) {
    val statuses = listOf("" to "Todas", "PENDIENTE" to "Pendientes", "APLICADA" to "Aplicadas")
    var editing by remember { mutableStateOf<RequisitionDto?>(null) }
    Column(Modifier.fillMaxSize()) {
        LazyRow(
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 12.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            items(statuses) { (value, label) ->
                FilterChip(
                    selected = state.requisitionStatus == value,
                    onClick = { viewModel.setRequisitionStatus(value) },
                    label = { Text(label) },
                )
            }
        }
        when {
            state.requisitionsLoading && state.requisitions.isEmpty() -> LoadingScreen()
            state.requisitionsError != null && state.requisitions.isEmpty() ->
                ErrorState(state.requisitionsError, viewModel::loadRequisitions)
            state.requisitions.isEmpty() -> EmptyState("No hay requisiciones en este estado")
            else -> LazyColumn(
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 4.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                items(state.requisitions, key = { it.id }) { item ->
                    RequisitionRow(
                        item,
                        onEdit = { editing = item },
                        onCancel = { viewModel.cancelRequisition(item) },
                    )
                }
            }
        }
    }

    editing?.let { item ->
        EditRequisitionDialog(
            item = item,
            submitting = state.submitting,
            onSubmit = { quantity ->
                viewModel.updateRequisition(item, quantity) { editing = null }
            },
            onDismiss = { editing = null },
        )
    }
}

@Composable
private fun RequisitionRow(
    item: RequisitionDto,
    onEdit: () -> Unit,
    onCancel: () -> Unit,
) {
    Surface(shape = MaterialTheme.shapes.medium, tonalElevation = 2.dp) {
        Column(Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Text(item.producto.ifBlank { item.codigo }, fontWeight = FontWeight.SemiBold)
                Text(
                    item.estado.ifBlank { "Sin estado" }.uppercase(),
                    color = if (item.estado.equals("pendiente", true)) {
                        MaterialTheme.colorScheme.primary
                    } else MaterialTheme.colorScheme.onSurfaceVariant,
                    style = MaterialTheme.typography.labelMedium,
                )
            }
            Spacer(Modifier.height(6.dp))
            Text("Cantidad: ${formatNumber(item.cantidad)}")
            if (item.sedeOrigen.isNotBlank()) Text("Origen: ${item.sedeOrigen}")
            if (item.createdAt.isNotBlank()) {
                Text(
                    item.createdAt,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            if (item.estado.equals("pendiente", ignoreCase = true)) {
                HorizontalDivider(Modifier.padding(vertical = 8.dp))
                Row(Modifier.align(Alignment.End)) {
                    TextButton(onClick = onEdit) { Text("Editar") }
                    TextButton(onClick = onCancel) {
                        Text("Cancelar", color = MaterialTheme.colorScheme.error)
                    }
                }
            }
        }
    }
}

@Composable
private fun EditRequisitionDialog(
    item: RequisitionDto,
    submitting: Boolean,
    onSubmit: (Double?) -> Unit,
    onDismiss: () -> Unit,
) {
    var quantity by remember(item.id) { mutableStateOf(formatNumber(item.cantidad)) }

    AlertDialog(
        onDismissRequest = { if (!submitting) onDismiss() },
        title = { Text("Editar requisición") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(item.producto.ifBlank { item.codigo }, fontWeight = FontWeight.SemiBold)
                Text("Origen: ${item.sedeOrigen}")
                OutlinedTextField(
                    value = quantity,
                    onValueChange = { quantity = it },
                    label = { Text("Cantidad") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    singleLine = true,
                )
            }
        },
        confirmButton = {
            Button(
                onClick = { onSubmit(quantity.toDoubleOrNull()) },
                enabled = !submitting,
            ) { Text(if (submitting) "Guardando…" else "Guardar") }
        },
        dismissButton = {
            TextButton(onClick = onDismiss, enabled = !submitting) { Text("Cerrar") }
        },
    )
}

@Composable
private fun EmptyState(message: String) {
    Box(Modifier.fillMaxSize().padding(24.dp), contentAlignment = Alignment.Center) {
        Text(message, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

@Composable
private fun ErrorState(message: String, onRetry: () -> Unit) {
    Column(
        modifier = Modifier.fillMaxSize().padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Text(message, color = MaterialTheme.colorScheme.error)
        Spacer(Modifier.height(12.dp))
        Button(onClick = onRetry) { Text("Reintentar") }
    }
}

private fun formatNumber(value: Double): String =
    if (value % 1.0 == 0.0) value.toLong().toString() else "%.2f".format(value)
