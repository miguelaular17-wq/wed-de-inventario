package com.example.inventario.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.example.inventario.data.InventarioItemDto
import com.example.inventario.data.MetricasDto
import com.example.inventario.data.PedidoProductDto
import com.example.inventario.data.RequisitionDto
import com.example.inventario.data.SedeDto
import com.example.inventario.ui.theme.NexoBlue
import com.example.inventario.ui.theme.NexoBlueDark
import com.example.inventario.ui.theme.NexoGradientBottom
import com.example.inventario.ui.theme.NexoGradientMid
import com.example.inventario.ui.theme.NexoGradientTop
import com.example.inventario.ui.theme.NexoMuted
import com.example.inventario.ui.theme.NexoSuccess
import com.example.inventario.ui.theme.NexoDanger

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
                when {
                    state.guestStockOpen -> GuestStockScreen(state = state, viewModel = viewModel)
                    state.guestPedidoOpen -> GuestPedidoScreen(state = state, viewModel = viewModel)
                    else -> LoginScreen(
                        loading = state.loginLoading,
                        error = state.loginError,
                        onLogin = viewModel::login,
                        onOpenStock = viewModel::openGuestStock,
                        onOpenPedido = viewModel::openGuestPedido,
                    )
                }
            }
            composable(HOME_ROUTE) {
                HomeScreen(state = state, viewModel = viewModel)
            }
        }
    }
}

@Composable
private fun LoadingScreen() {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background),
        contentAlignment = Alignment.Center,
    ) {
        CircularProgressIndicator(color = MaterialTheme.colorScheme.primary)
    }
}

@Composable
private fun LoginScreen(
    loading: Boolean,
    error: String?,
    onLogin: (String, String) -> Unit,
    onOpenStock: () -> Unit,
    onOpenPedido: () -> Unit,
) {
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(NexoGradientTop, NexoGradientMid, NexoGradientBottom),
                ),
            ),
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 24.dp, vertical = 32.dp),
            verticalArrangement = Arrangement.SpaceBetween,
        ) {
            Column(
                modifier = Modifier.padding(top = 36.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                Box(
                    modifier = Modifier
                        .size(52.dp)
                        .clip(RoundedCornerShape(14.dp))
                        .background(Color.White.copy(alpha = 0.14f))
                        .border(1.dp, Color.White.copy(alpha = 0.22f), RoundedCornerShape(14.dp)),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        "N",
                        style = MaterialTheme.typography.headlineMedium,
                        color = Color.White,
                        fontWeight = FontWeight.Bold,
                    )
                }
                Text(
                    "Nexo PD",
                    style = MaterialTheme.typography.displaySmall,
                    color = Color.White,
                )
                Text(
                    "Servicio técnico · registro y seguimiento",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.78f),
                )
            }

            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(22.dp),
                    color = Color.White,
                    shadowElevation = 10.dp,
                ) {
                    Column(
                        modifier = Modifier.padding(22.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp),
                    ) {
                        Text(
                            "Iniciar sesión",
                            style = MaterialTheme.typography.titleLarge,
                            color = NexoBlueDark,
                        )
                        Text(
                            "Usa tu correo de Nexo para continuar",
                            style = MaterialTheme.typography.bodySmall,
                            color = NexoMuted,
                        )
                        OutlinedTextField(
                            value = email,
                            onValueChange = { email = it },
                            label = { Text("Correo") },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = NexoBlue,
                                unfocusedBorderColor = MaterialTheme.colorScheme.outline,
                            ),
                            modifier = Modifier.fillMaxWidth(),
                        )
                        OutlinedTextField(
                            value = password,
                            onValueChange = { password = it },
                            label = { Text("Contraseña") },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                            visualTransformation = PasswordVisualTransformation(),
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = NexoBlue,
                                unfocusedBorderColor = MaterialTheme.colorScheme.outline,
                            ),
                            modifier = Modifier.fillMaxWidth(),
                        )
                        error?.let {
                            Text(
                                it,
                                color = MaterialTheme.colorScheme.error,
                                style = MaterialTheme.typography.bodySmall,
                            )
                        }
                        Button(
                            onClick = { onLogin(email, password) },
                            enabled = !loading,
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = NexoBlue),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(50.dp),
                        ) {
                            if (loading) {
                                CircularProgressIndicator(
                                    modifier = Modifier.size(20.dp),
                                    strokeWidth = 2.dp,
                                    color = Color.White,
                                )
                            } else {
                                Text("Entrar", fontWeight = FontWeight.SemiBold)
                            }
                        }
                    }
                }

                OutlinedButton(
                    onClick = onOpenStock,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.outlinedButtonColors(
                        contentColor = Color.White,
                    ),
                    border = androidx.compose.foundation.BorderStroke(
                        1.dp,
                        Color.White.copy(alpha = 0.45f),
                    ),
                ) {
                    Text("Ver existencias", fontWeight = FontWeight.SemiBold)
                }
                OutlinedButton(
                    onClick = onOpenPedido,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.outlinedButtonColors(
                        contentColor = Color.White,
                    ),
                    border = androidx.compose.foundation.BorderStroke(
                        1.dp,
                        Color.White.copy(alpha = 0.45f),
                    ),
                ) {
                    Text("Q Pedir", fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun GuestPedidoScreen(state: AppUiState, viewModel: AppViewModel) {
    val sedesPedido = listOf("DORAL", "VIRTUDES", "ZAMORA", "CENTRO", "SAMBIL", "NUNES")
    var sedeMenuOpen by remember { mutableStateOf(false) }
    var categoriaMenuOpen by remember { mutableStateOf(false) }

    Scaffold(
        containerColor = MaterialTheme.colorScheme.background,
        topBar = {
            TopAppBar(
                windowInsets = WindowInsets(0, 0, 0, 0),
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = NexoBlueDark,
                    titleContentColor = Color.White,
                ),
                title = {
                    Column {
                        Text(
                            "Q Pedir",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = Color.White,
                        )
                        Text(
                            "Solicitar producto a compras",
                            style = MaterialTheme.typography.labelSmall,
                            color = Color.White.copy(alpha = 0.72f),
                        )
                    }
                },
                navigationIcon = {
                    TextButton(onClick = viewModel::closeGuestPedido) {
                        Text("←", color = Color.White, style = MaterialTheme.typography.titleLarge)
                    }
                },
            )
        },
    ) { padding ->
        Column(
            Modifier
                .padding(padding)
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text(
                "Busca el producto que necesitas y envíalo al equipo de compras.",
                style = MaterialTheme.typography.bodyMedium,
                color = NexoMuted,
            )

            OutlinedTextField(
                value = state.guestPedidoQuery,
                onValueChange = viewModel::setGuestPedidoQuery,
                label = { Text("Buscar producto") },
                placeholder = { Text("Código o nombre…") },
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.fillMaxWidth(),
            )

            if (state.guestPedidoSearching) {
                Row(
                    Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.Center,
                ) {
                    CircularProgressIndicator(modifier = Modifier.size(22.dp), strokeWidth = 2.dp)
                }
            }

            if (state.guestPedidoSelected == null) {
                if (state.guestPedidoResults.isNotEmpty()) {
                    state.guestPedidoResults.forEach { product ->
                        Surface(
                            onClick = { viewModel.selectGuestPedidoProduct(product) },
                            shape = RoundedCornerShape(12.dp),
                            color = Color.White,
                            shadowElevation = 1.dp,
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Row(
                                    Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                ) {
                                    Text(
                                        product.producto,
                                        fontWeight = FontWeight.SemiBold,
                                        modifier = Modifier.weight(1f),
                                    )
                                    Text(
                                        "Stock: ${product.stock}",
                                        color = if (product.stock == 0) NexoDanger else NexoSuccess,
                                        style = MaterialTheme.typography.labelMedium,
                                    )
                                }
                                Text(
                                    listOfNotNull(
                                        product.codigo.takeIf { it.isNotBlank() },
                                        product.categoria?.takeIf { it.isNotBlank() },
                                    ).joinToString(" · "),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = NexoMuted,
                                )
                            }
                        }
                    }
                } else if (state.guestPedidoQuery.trim().length >= 2 && !state.guestPedidoSearching) {
                    Text("No se encontraron productos.", color = NexoMuted)
                    OutlinedButton(
                        onClick = viewModel::selectGuestPedidoManual,
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                    ) {
                        Text("Agregar manualmente: ${state.guestPedidoQuery.trim().uppercase()}")
                    }
                }
            }

            state.guestPedidoSelected?.let { selected ->
                Surface(
                    shape = RoundedCornerShape(16.dp),
                    color = Color.White,
                    shadowElevation = 1.dp,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Column(
                        Modifier.padding(14.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        Text(
                            "Producto seleccionado",
                            style = MaterialTheme.typography.labelMedium,
                            color = NexoMuted,
                        )
                        Text(
                            if (state.guestPedidoManual) {
                                "${selected.producto} (MANUAL)"
                            } else {
                                "${selected.producto} (${selected.codigo}) — Stock: ${selected.stock}"
                            },
                            fontWeight = FontWeight.SemiBold,
                            color = NexoBlueDark,
                        )
                        TextButton(onClick = viewModel::clearGuestPedidoSelection) {
                            Text("Cambiar producto")
                        }

                        if (state.guestPedidoManual) {
                            Box {
                                OutlinedButton(
                                    onClick = { categoriaMenuOpen = true },
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(12.dp),
                                ) {
                                    Text(
                                        state.guestPedidoCategory.ifBlank { "Categoría (obligatorio)" },
                                    )
                                }
                                DropdownMenu(
                                    expanded = categoriaMenuOpen,
                                    onDismissRequest = { categoriaMenuOpen = false },
                                ) {
                                    state.guestPedidoCategories.forEach { cat ->
                                        DropdownMenuItem(
                                            text = { Text(cat) },
                                            onClick = {
                                                viewModel.setGuestPedidoCategory(cat)
                                                categoriaMenuOpen = false
                                            },
                                        )
                                    }
                                }
                            }
                        }

                        OutlinedTextField(
                            value = state.guestPedidoSolicitante,
                            onValueChange = viewModel::setGuestPedidoSolicitante,
                            label = { Text("Tu nombre (opcional)") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth(),
                        )

                        Box {
                            OutlinedButton(
                                onClick = { sedeMenuOpen = true },
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(12.dp),
                            ) {
                                Text(
                                    if (state.guestPedidoSede.isBlank()) "Sede"
                                    else "Sede: ${state.guestPedidoSede}",
                                )
                            }
                            DropdownMenu(
                                expanded = sedeMenuOpen,
                                onDismissRequest = { sedeMenuOpen = false },
                            ) {
                                DropdownMenuItem(
                                    text = { Text("Sin sede") },
                                    onClick = {
                                        viewModel.setGuestPedidoSede("")
                                        sedeMenuOpen = false
                                    },
                                )
                                sedesPedido.forEach { sede ->
                                    DropdownMenuItem(
                                        text = { Text(sede) },
                                        onClick = {
                                            viewModel.setGuestPedidoSede(sede)
                                            sedeMenuOpen = false
                                        },
                                    )
                                }
                            }
                        }

                        OutlinedTextField(
                            value = state.guestPedidoNotas,
                            onValueChange = viewModel::setGuestPedidoNotas,
                            label = { Text("Notas (opcional)") },
                            placeholder = { Text("Cantidad, urgencia…") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth(),
                        )

                        Button(
                            onClick = viewModel::submitGuestPedido,
                            enabled = !state.guestPedidoSubmitting,
                            modifier = Modifier.fillMaxWidth().height(48.dp),
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = NexoBlue),
                        ) {
                            if (state.guestPedidoSubmitting) {
                                CircularProgressIndicator(
                                    modifier = Modifier.size(20.dp),
                                    strokeWidth = 2.dp,
                                    color = Color.White,
                                )
                            } else {
                                Text("Guardar solicitud", fontWeight = FontWeight.SemiBold)
                            }
                        }
                    }
                }
            }

            state.guestPedidoMessage?.let {
                Text(it, color = NexoSuccess, fontWeight = FontWeight.SemiBold)
            }
            state.guestPedidoError?.let {
                Text(it, color = NexoDanger)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun GuestStockScreen(state: AppUiState, viewModel: AppViewModel) {
    var siteMenuOpen by remember { mutableStateOf(false) }

    Scaffold(
        containerColor = MaterialTheme.colorScheme.background,
        topBar = {
            TopAppBar(
                windowInsets = WindowInsets(0, 0, 0, 0),
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = NexoBlueDark,
                    titleContentColor = Color.White,
                ),
                title = {
                    Column {
                        Text(
                            "Existencias",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = Color.White,
                        )
                        Text(
                            "Consulta sin iniciar sesión",
                            style = MaterialTheme.typography.labelSmall,
                            color = Color.White.copy(alpha = 0.72f),
                        )
                    }
                },
                navigationIcon = {
                    TextButton(onClick = viewModel::closeGuestStock) {
                        Text("←", color = Color.White, style = MaterialTheme.typography.titleLarge)
                    }
                },
            )
        },
    ) { padding ->
        Column(
            Modifier
                .padding(padding)
                .fillMaxSize(),
        ) {
            Column(
                Modifier.padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                Box {
                    OutlinedButton(
                        onClick = { siteMenuOpen = true },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                    ) {
                        Text("Sede: ${state.guestActiveSite?.displayName ?: "Elegir"}")
                    }
                    DropdownMenu(
                        expanded = siteMenuOpen,
                        onDismissRequest = { siteMenuOpen = false },
                    ) {
                        state.guestSites.forEach { site ->
                            DropdownMenuItem(
                                text = { Text(site.displayName) },
                                onClick = {
                                    viewModel.setGuestSite(site)
                                    siteMenuOpen = false
                                },
                            )
                        }
                    }
                }
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    OutlinedTextField(
                        value = state.guestSearch,
                        onValueChange = viewModel::setGuestSearch,
                        label = { Text("Código o producto") },
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp),
                        modifier = Modifier.weight(1f),
                    )
                    Button(
                        onClick = viewModel::searchGuestStock,
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = NexoBlue),
                        modifier = Modifier.height(56.dp),
                    ) {
                        Text("Buscar")
                    }
                }
            }

            when {
                state.guestInventoryLoading && state.guestInventory.isEmpty() -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(color = NexoBlue)
                    }
                }
                state.guestInventoryError != null && state.guestInventory.isEmpty() -> {
                    Column(
                        Modifier.fillMaxSize().padding(24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center,
                    ) {
                        Text(state.guestInventoryError, color = MaterialTheme.colorScheme.error)
                        Spacer(Modifier.height(12.dp))
                        Button(onClick = viewModel::searchGuestStock) { Text("Reintentar") }
                    }
                }
                state.guestInventory.isEmpty() -> {
                    Box(Modifier.fillMaxSize().padding(24.dp), contentAlignment = Alignment.Center) {
                        Text("No hay productos para mostrar", color = NexoMuted)
                    }
                }
                else -> LazyColumn(
                    contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    items(state.guestInventory, key = { it.codigo }) { item ->
                        GuestStockRow(item)
                    }
                    item {
                        if (state.guestInventoryPage < state.guestInventoryLastPage) {
                            OutlinedButton(
                                onClick = viewModel::loadMoreGuestStock,
                                enabled = !state.guestInventoryLoading,
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(12.dp),
                            ) {
                                Text(if (state.guestInventoryLoading) "Cargando…" else "Cargar más")
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun GuestStockRow(item: InventarioItemDto) {
    Surface(
        shape = RoundedCornerShape(16.dp),
        color = Color.White,
        shadowElevation = 1.dp,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Column(
            Modifier.padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            Text(
                item.displayName,
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.SemiBold,
                color = NexoBlueDark,
            )
            Text(
                item.codigo,
                style = MaterialTheme.typography.labelMedium,
                color = NexoBlue,
            )
            if (item.categoria.isNotBlank()) {
                Text(
                    item.categoria,
                    style = MaterialTheme.typography.bodySmall,
                    color = NexoMuted,
                )
            }
            Text(
                "Existencia sede: ${formatNumber(item.available)}",
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = FontWeight.SemiBold,
            )
            if (item.stocks.isNotEmpty()) {
                Text(
                    item.stocks.entries
                        .sortedBy { it.key }
                        .joinToString(" · ") { "${it.key}: ${formatNumber(it.value)}" },
                    style = MaterialTheme.typography.bodySmall,
                    color = NexoMuted,
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun HomeScreen(state: AppUiState, viewModel: AppViewModel) {
    var selectedTab by remember { mutableIntStateOf(0) }
    val tabs = listOf("Consultar" to "⌕", "Registrar" to "+")

    Scaffold(
        containerColor = MaterialTheme.colorScheme.background,
        topBar = {
            TopAppBar(
                windowInsets = WindowInsets(0, 0, 0, 0),
                expandedHeight = 56.dp,
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = NexoBlueDark,
                    titleContentColor = Color.White,
                    actionIconContentColor = Color.White,
                ),
                title = {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        Box(
                            modifier = Modifier
                                .size(30.dp)
                                .clip(RoundedCornerShape(8.dp))
                                .background(Color.White.copy(alpha = 0.14f)),
                            contentAlignment = Alignment.Center,
                        ) {
                            Text(
                                "N",
                                color = Color.White,
                                fontWeight = FontWeight.Bold,
                                style = MaterialTheme.typography.labelLarge,
                            )
                        }
                        Column {
                            Text(
                                "Nexo PD",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                                color = Color.White,
                            )
                            Text(
                                state.user?.displayName.orEmpty(),
                                style = MaterialTheme.typography.labelSmall,
                                color = Color.White.copy(alpha = 0.72f),
                            )
                        }
                    }
                },
                actions = {
                    TextButton(
                        onClick = viewModel::logout,
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 4.dp),
                    ) {
                        Text(
                            "Salir",
                            color = Color.White.copy(alpha = 0.9f),
                            style = MaterialTheme.typography.labelMedium,
                        )
                    }
                },
            )
        },
        bottomBar = {
            NavigationBar(
                modifier = Modifier.height(64.dp),
                windowInsets = WindowInsets(0, 0, 0, 0),
                containerColor = Color.White,
                tonalElevation = 0.dp,
            ) {
                tabs.forEachIndexed { index, (label, glyph) ->
                    val selected = selectedTab == index
                    NavigationBarItem(
                        selected = selected,
                        onClick = {
                            selectedTab = index
                            if (index == 1) {
                                viewModel.closeServiceOrder()
                                viewModel.closeReceptionPdf()
                            }
                        },
                        icon = {
                            Box(
                                modifier = Modifier
                                    .size(if (selected) 34.dp else 30.dp)
                                    .clip(CircleShape)
                                    .background(
                                        if (selected) NexoBlue.copy(alpha = 0.12f)
                                        else Color.Transparent,
                                    ),
                                contentAlignment = Alignment.Center,
                            ) {
                                Text(
                                    glyph,
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.Bold,
                                    color = if (selected) NexoBlue else NexoMuted,
                                )
                            }
                        },
                        label = {
                            Text(
                                label,
                                style = MaterialTheme.typography.labelSmall,
                                fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
                            )
                        },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor = NexoBlue,
                            selectedTextColor = NexoBlue,
                            unselectedIconColor = NexoMuted,
                            unselectedTextColor = NexoMuted,
                            indicatorColor = Color.Transparent,
                        ),
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
            shape = RoundedCornerShape(12.dp),
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
    Surface(
        shape = MaterialTheme.shapes.medium,
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = 0.dp,
        shadowElevation = 1.dp,
    ) {
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
                Button(onClick = onRequest, shape = RoundedCornerShape(10.dp)) { Text("Solicitar") }
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
                    onValueChange = { quantityText = it },
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
