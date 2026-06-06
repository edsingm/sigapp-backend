function comproterrenoTerrenosResponsavelSalvar(){
	$('.btn-formulario').prop('disabled', true);

	$.ajax({
		url: $.trim($('#terrenos_atualizar_responsavel').val())+"/"+$.trim($('#TER_ID').val()),
		dataType: 'json',
		cache: false,
		data: {
			TER_Responsavel: $.trim($('#TER_Responsavel').val())
		},
		type: 'POST',
	}).success(function(data){
		$('.btn-formulario').prop('disabled', false);

		if (data.error){
			dialogAlert(strAtencao, data.error.msg, 6);
			return;
		}

		$.notify(data.mensagem, "success");

	}).fail(function(data){
		$('.btn-formulario').prop('disabled', false);
		dialogAlert(strAtencao, data.responseText, 6);
	});
}

function initComproterrenoGestores(){
	$('#GEU_Tipo').selectpicker({liveSearch: true});

	$("#GEU_Tipo").change(function (e){
		var valor = $.trim(this.value);

		if (valor != ''){								
			$(this).prop('disabled', true);

			$.ajax({
				url: $.trim($('#gestores_atualizar_tipo').val()),
				dataType: 'json',
				cache: false,
				data: {
					CAX_ID: $.trim($('#hddCadastroID').val()),
					GEU_Tipo: valor
				},
				type: 'POST',
			}).success(function(data){
				$(this).prop('disabled', false);
		
				if (data.error){
					dialogAlert(strAtencao, data.error.msg, 6);
					return;
				}
		
				$.notify(data.mensagem, "success");
		
			}).fail(function(data){
				$(this).prop('disabled', false);
				dialogAlert(strAtencao, data.responseText, 6);
			});
		}
	});

	$("#USU_Gestor_ID").change(function (e){
		var valor = $.trim(this.value);

		if (valor != ''){								
			$(this).prop('disabled', true);

			$.ajax({
				url: $.trim($('#gestores_atualizar_terrenos').val()),
				dataType: 'json',
				cache: false,
				data: {
					CAX_ID: $.trim($('#hddCadastroID').val()),
					USU_Gestor_ID: valor
				},
				type: 'POST',
			}).success(function(data){
				$(this).prop('disabled', false);
		
				if (data.error){
					dialogAlert(strAtencao, data.error.msg, 6);
					return;
				}
		
				$.notify(data.mensagem, "success");
		
			}).fail(function(data){
				$(this).prop('disabled', false);
				dialogAlert(strAtencao, data.responseText, 6);
			});
		}
	});
	
	$('.modal').on('hidden.bs.modal', function (){
		redir('', 'parent');
	});
}

function comproterrenoTerrenosAtividades(){
	var strLabel 			= consultarPadraoInicial();
	var arrStatus  			= new Array();
	var arrCadastrosGlobais = new Array();
	var arrResponsaveis     = new Array();
	var arrTerrenos         = new Array();

	$("select[name='CAG_ID[]'] option:selected").each(function(){
		arrCadastrosGlobais.push($(this).val());
	});
	
	$("select[name='SGP_Status[]'] option:selected").each(function(){
		arrStatus.push($(this).val());
	});
	
	$("select[name='USU_ID[]'] option:selected").each(function(){
		arrResponsaveis.push($(this).val());
	});

	if ($('#TER_ID').prop('multiple')){		
		$("select[name='TER_ID[]'] option:selected").each(function(){
			arrTerrenos.push($(this).val());
		});
	}else{
		arrTerrenos.push($.trim($('#TER_ID').val()));
	}

	$('#spnBtnAtividades').hide();

	$.ajax({
		url: $.trim($('#terrenos_atividades_consultar').val()),
		dataType: 'json',
		cache: false,
		data: {
			SGP_Status: arrStatus,
			CAG_ID: arrCadastrosGlobais,
			USU_Responsavel_ID: arrResponsaveis,
			TER_ID: arrTerrenos,
			SGP_Pesquisar: $.trim($('#SGP_Pesquisar').val()),
			SGP_DataInicial: $.trim($('#txtDataInicial').val()),
			SGP_DataFinal: $.trim($('#txtDataFinal').val()),
			SGP_DataInicial: $.trim($('#txtDataAtividadeInicial').val()),
			SGP_DataFinal: $.trim($('#txtDataAtividadeFinal').val())
		},
		type: 'POST',
	}).success(function(data){
		consultarPadraoSucesso(strLabel);

		if (data.error){			
			consultarPadraoExcessao();
			dialogAlert(strInformacao, data.error.msg, 6);
			return;
		}

		consultarPadraoSucessoPaginacao(data);

		if ($.trim(data.htmlBotaoAtividades) != ""){
			$('#spnBtnAtividades').html($.trim(data.htmlBotaoAtividades));
			$('#spnBtnAtividades').show();
		}

	}).fail(function(data){
		consultarPadraoFalha(strLabel);
		dialogAlert(strAtencao, data.responseText, 6);
	});	
}

function comproterrenoTerrenosAtividadesSalvar(){
	if ($.trim($('#CAG_Dialog_ID').val()) == ""){
		$.notify("Tipo da atividade precisa ser informada.", "warn");
		return;
	}else if ($.trim($('#ATI_Dialog_Data').val()) == ""){
		$.notify("Data da atividade precisa ser informada.", "warn");
		return;
	}else{
		$('#anexos-adicionados').html(strCarregando);
		var strLabel = consultarPadraoInicial(false);
		var form = $('#frmFormularioDialog')[0];
        var formData = new FormData(form);

		formData.set('ATI_Dialog_Observacoes', $("iframe").contents().find(".wysihtml5-editor")[0].innerText);

		$.ajax({
			url: $.trim($('#terrenos_atividades_salvar').val()),
			dataType: 'json',
			cache: false,
			processData: false,
			contentType: false,
			data: formData,
			type: 'POST',
		}).success(function(data){

			consultarPadraoSucesso(strLabel, false);	
			if (data.error){			
				$('#anexos-adicionados').html('');
				consultarPadraoExcessao();
				dialogAlert(strInformacao, data.error.msg, 6);
				return;
			}

			$.notify(data.mensagem, "success");
			$('#anexos-adicionados').html(data.strHtmlAnexos);
			$('#SGP_Dialog_ID').val(data.intCodigo);
			$('#TER_Dialog_ID').val(data.intTerreno);

			$('#SGP_QuantidadeAnexos').val('');
			$('#SGP_QuantidadeAnexos').trigger('change');

			comproterrenoTerrenosAtividades();

		}).fail(function(data){
			$('#anexos-adicionados').html('');
			consultarPadraoFalha(strLabel, false);
			dialogAlert(strAtencao, data.responseText, 6);
		});		
	}
}

function comproterrenoAtividadesAnexosConsultar(terreno = ''){
	$('#anexos-adicionados').html('');

	$.ajax({
		url: $.trim($('#terrenos_atividades_consultar_anexos').val())+"/"+$.trim($('#SGP_Dialog_ID').val()),
		dataType: 'json',
		cache: false,
		data: {
			TER_ID: $.trim($('#TER_ID').val())
		},
		type: 'POST',
	}).success(function(data){		
		if (data.error){
			$("#anexos-adicionados").html(data.strHtml);
			dialogAlert(strAtencao, data.error.msg, 6);
			return;
		}

		$("#anexos-adicionados").html(data.strHtml);

	}).fail(function(data){
		$('#boxAtividades').hide();
		$('#divAtividades').html('');
		dialogAlert(strAtencao, data.responseText, 6);
	});
}

function limparComproterrenoTerrenosAtividades(){
	$('#CAG_Dialog_ID, #TER_Dialog_ID, #ATI_Dialog_Data, #USU_Responsavel_Dialog_ID, #SGP_Dialog_ID, #ATI_Dialog_Observacoes, #USU_Responsavel_Dialog_ID, #ATI_Dialog_Descricao').val("");
	$('#CAG_Dialog_ID, #TER_Dialog_ID, #ATI_Dialog_Data, #USU_Responsavel_Dialog_ID, #SGP_Dialog_ID, #ATI_Dialog_Observacoes').selectpicker("refresh");
	$('#SGP_Concluido').prop('checked', false);
}

function initComproterrenoTerrenosAtividades(terreno = ''){
    $('input[type="checkbox"].flat-red').iCheck({
		checkboxClass: 'icheckbox_flat-green'
	});

	$('.textarea').wysihtml5({toolbar: 'none'});

	if (!$('#TER_ID').prop('multiple')){
		$('select[name="TER_Dialog_ID"]').prop("disabled", true);
		$('select[name="TER_Dialog_ID"]').selectpicker("refresh");
	}

	comproterrenoAtividadesAnexosConsultar(terreno);
}

function comproterrenoTerrenosAtividadesVisualizar(){
	$('#boxAtividades').show();
	$("#divAtividades").html(strCarregando);

	$.ajax({
		url: $.trim($('#terrenos_atividades_visualizar').val()),
		dataType: 'json',
		cache: false,
		data: {
			TER_ID: $.trim($('#TER_ID').val())
		},
		type: 'POST',
	}).success(function(data){
		$("#divAtividades").html(data.strHtml);

	}).fail(function(data){
		$('#boxAtividades').hide();
		$('#divAtividades').html('');
		dialogAlert(strAtencao, data.responseText, 6);
	});	
}

function comproterrenoCorretores(){
	var strLabel   = consultarPadraoInicial();
    var arrEstados = new Array();

    $("select[name='UF_ID[]'] option:selected").each(function(){
	   arrEstados.push($(this).val());
    });

    $.ajax({
		url: $.trim($('#corretores_resultado').val()),
		dataType: 'json',
		cache: false,
		data: {
			UF_ID: arrEstados,
			SGP_Pesquisar: $.trim($('#SGP_Pesquisar').val())
		},
		type: 'POST',
	}).success(function(data){
		consultarPadraoSucesso(strLabel);
		if (data.error){
			consultarPadraoExcessao();
			dialogAlert(strAtencao, data.error.msg, 6);
			return;
		}

		consultarPadraoSucessoPaginacao(data);

	}).fail(function(data){
		consultarPadraoFalha(strLabel);
		dialogAlert(strAtencao, data.responseText, 6);
	});	
}

function comproterrenoTerrenosAtualizarData(){
	if ($.trim($('#TER_DataHoraCadastro').val()) == ""){
		$.notify("Data do cadastro precisa ser informada.", "warn");
		return;
	} 

	$("#btnSalvarTerrenoDataCadastro").prop('disabled', true);
	var strLabel = $("#btnSalvarTerrenoDataCadastro").html();
	$("#btnSalvarTerrenoDataCadastro").html(strCarregando);

	$.ajax({
		url: $.trim($('#terrenos_data_cadastro_atualizar').val()),
		dataType: 'json',
		cache: false,
		data: {
			TER_ID: $.trim($('#hddCodigoTerreno').val()),
			TER_DataHoraCadastro: $.trim($('#TER_DataHoraCadastro').val())
		},
		type: 'POST',
	}).success(function (data) {
		$("#btnSalvarTerrenoDataCadastro").html(strLabel);
		$("#btnSalvarTerrenoDataCadastro").prop('disabled', false);

		if (data.error) {
			dialogAlert(strAtencao, data.error.msg, 6);
			return;
		}

		$(".modal").modal("hide");
		$.notify(data.mensagem, "success");
		$('#btnFiltrar').trigger('click');

	}).fail(function (data) {
		$("#btnSalvarTerrenoDataCadastro").html(strLabel);
		$("#btnSalvarTerrenoDataCadastro").prop('disabled', false);
		dialogAlert(strAtencao, data.responseText, 6);
	});
}