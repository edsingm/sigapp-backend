var strCarregando = $.trim($("#hddCarregando").val());
var strCarregandoCor = $.trim($("#hddCarregandoCor").val());
var strCarregandoIcone = $.trim($("#hddCarregandoIcone").val());
var strSelecione = $.trim($("#hddSelecione").val());
var strAtencao = $.trim($("#hddInfoError").val());
var strInformacao = $.trim($("#hddInfoAlert").val());
var strDiretorioDocumentos = $.trim($("#hddDirDocs").val());
var strLabelEnderecoNaoLocalizado = $.trim($("#hddLabelEnderecoNaoLocalizado").val());
var strCaminhoProjeto = $.trim($("#hddPathProject").val());
var strSemDados = $.trim($("#hddSemDados").val());
var strSeparadorMetodo = $.trim($("#hddMethodSeparator").val());
var strSeparadorLog = $.trim($("#hddLogSeparator").val());
var strSim = $.trim($("#hddSim").val());
var strNao = $.trim($("#hddNao").val());
var strLabelSim = $.trim($("#hddLabelSim").val());
var strLabelNao = $.trim($("#hddLabelNao").val());
var strLabelItemSelecionado = $.trim($("#hddLabelItemSelecionado").val());
var strLabelFiltroPesquisarItens = $.trim($("#hddLabelFiltroPesquisarItensContratos").val());
var strConfirmarOK = $.trim($("#hddConfirmarOK").val());
var strSelecioneMinimo = $.trim($("#hddSelecioneNoMinino").val());
var strTodasOpcoes = $.trim($("#hddTodasOpcoes").val());
var strTodos = $.trim($("#hddTodosLabel").val());
var strBootstrapCodigoDefault = $.trim($("#hddBootstrapCodigoDefault").val());
var strBootstrapCodigoInfo = $.trim($("#hddBootstrapCodigoInfo").val());
var strBootstrapCodigoPrimary = $.trim($("#hddBootstrapCodigoPrimary").val());
var strBootstrapCodigoSuccess = $.trim($("#hddBootstrapCodigoSuccess").val());
var strBootstrapCodigoWarning = $.trim($("#hddBootstrapCodigoWarning").val());
var strBootstrapCodigoDanger = $.trim($("#hddBootstrapCodigoDanger").val());
var strTipoGraficoColunasBasico = $.trim($("#hddTipoGraficoColunasBasico").val());
var strTipoGraficoColunasColocacao = $.trim($("#hddTipoGraficoColunasColocacao").val());
var strTipoGraficoColunasLinhasPizza = $.trim($("#hddTipoGraficoColunasLinhasPizza").val());
var strDataInvalida = $.trim($("#hddDataInvalida").val());
var strHtml = "";

function voltar() {
  history.back(-1);
}

function setInitFunctions() {

  /*  CONFIGURAÇÕES DOS CAMPOS COM APLICAÇÃO INPUTMASK - SUFIXO INDICA NÚMERO DE CASAS DECIMAIS 
    PARA REMOVER A MASCARA E PEGAR O VALOR SEM FORMATAÇÃO, UTILIZAR O TRECHO A SEGUIR		
    Ex: $('#SIT_QuantidadeSolicitadas').inputmask('unmaskedvalue'); 
  */

  $('.inputMask_2').inputmask('currency', {
    alias: 'numeric',
    radixPoint: ',',
    groupSeparator: '.',
    autoGroup: true,
    digits: 2,
    digitsOptional: false,
    placeholder: '0',
    rightAlign: false,
    allowMinus: false
  });

  $('.inputMask_3').inputmask('currency', {
    alias: 'numeric',
    radixPoint: ',',
    groupSeparator: '.',
    autoGroup: true,
    digits: 3,
    digitsOptional: false,
    placeholder: '0',
    rightAlign: false,
    allowMinus: false
  });

  $('.inputMask_4').inputmask('currency', {
    alias: 'numeric',
    radixPoint: ',',
    groupSeparator: '.',
    autoGroup: true,
    digits: 4,
    digitsOptional: false,
    placeholder: '0',
    rightAlign: false,
    allowMinus: false
  });

  $('.inputMask_5').inputmask('currency', {
    alias: 'numeric',
    radixPoint: ',',
    groupSeparator: '.',
    autoGroup: true,
    digits: 5,
    digitsOptional: false,
    placeholder: '0',
    rightAlign: false,
    allowMinus: false
  });

  $('.inputMask_6').inputmask('currency', {
    alias: 'numeric',
    radixPoint: ',',
    groupSeparator: '.',
    autoGroup: true,
    digits: 6,
    digitsOptional: false,
    placeholder: '0',
    rightAlign: false,
    allowMinus: false
  });

  $('.inputMask_7').inputmask('currency', {
    alias: 'numeric',
    radixPoint: ',',
    groupSeparator: '.',
    autoGroup: true,
    digits: 7,
    digitsOptional: false,
    placeholder: '0',
    rightAlign: false,
    allowMinus: false
  });

  $('.inputMask_8').inputmask('currency', {
    alias: 'numeric',
    radixPoint: ',',
    groupSeparator: '.',
    autoGroup: true,
    digits: 8,
    digitsOptional: false,
    placeholder: '0',
    rightAlign: false,
    allowMinus: false
  });

  /* FIM CONFIGURAÇÕES INPUTMASK */


  $('[data-toggle="tooltip"]').tooltip({ html: true });
  $(".maskMoney").maskMoney({
    showSymbol: false,
    symbol: "R$",
    decimal: ",",
    thousands: ".",
    allowZero: true,
    defaultZero: false,
  });
  $(".maskMoney3").maskMoney({
    showSymbol: false,
    symbol: "R$",
    precision: 3,
    decimal: ",",
    thousands: ".",
    allowZero: true,
    defaultZero: false,
    allowNegative: true,
  });
  $(".maskMoney4").maskMoney({
    showSymbol: false,
    symbol: "R$",
    precision: 4,
    decimal: ",",
    thousands: ".",
    allowZero: true,
    defaultZero: false,
    allowNegative: true,
  });
  $(".maskMoney5").maskMoney({
    showSymbol: false,
    symbol: "R$",
    precision: 5,
    decimal: ",",
    thousands: ".",
    allowZero: true,
    defaultZero: false,
    allowNegative: true,
  });
  $(".maskMoney6").maskMoney({
    showSymbol: false,
    symbol: "R$",
    precision: 6,
    decimal: ",",
    thousands: ".",
    allowZero: true,
    defaultZero: false,
    allowNegative: true,
  });
  $(".maskMoney7").maskMoney({
    showSymbol: false,
    symbol: "R$",
    precision: 7,
    decimal: ",",
    thousands: ".",
    allowZero: true,
    defaultZero: false,
    allowNegative: true,
  });
  $(".maskMoney8").maskMoney({
    showSymbol: false,
    symbol: "R$",
    precision: 8,
    decimal: ",",
    thousands: ".",
    allowZero: true,
    defaultZero: false,
    allowNegative: true,
  });
  $(".maskCNPJ").mask("99.999.999/9999-99", { reverse: true });
  $(".maskCPF").mask("999.999.999-99", { reverse: true });
  $(".maskTelefone").mask("(99)9999-9999", { reverse: true });
  $(".maskCelular").mask("(99)99999-9999", { reverse: true });
  $(".maskCEP").mask("99999-999");
  $(".maskCompetencia").mask("99/9999");
  $(".maskData").mask("99/99/9999");
  $(".maskTime").mask("99:99");
  $(".multiplos").multiselect(getOptions());

  //Apenas números input css
  $(".numericOnly").on("keypress keyup blur", function (event) {
    $(this).val(
      $(this)
        .val()
        .replace(/[^A-Z\.][^0-9\.]/g, "")
    );
    if (
      (event.which != 46 || $(this).val().indexOf(".") != -1) &&
      (event.which < 48 || event.which > 57)
    ) {
      event.preventDefault();
    }
  });

  $(".modal-dialog").draggable({
    handle: ".modal-header",
  });

  $(".noTrim").keypress(function (e) {
    if (e.which === 32) return false;
  });

  $(".modal").on("hidden.bs.modal", function () {
    preLoadingClose();
  });

  //Selectpicker Bootstrap
  $(".selectpicker.select-all")
    .on("change", function () {
      var selectPicker = $(this);
      var selectAllOption = selectPicker.find("option.select-all");
      var checkedAll = selectAllOption.prop("selected");
      var optionValues = selectPicker.find(
        'option[value!="[all]"][data-divider!="true"]'
      );

      if (checkedAll) {
        // Process 'all/none' checking
        var allChecked = selectAllOption.data("all") || false;

        if (!allChecked) {
          optionValues.prop("selected", true).parent().selectpicker("refresh");
          selectAllOption.data("all", true);
        } else {
          optionValues.prop("selected", false).parent().selectpicker("refresh");
          selectAllOption.data("all", false);
        }

        selectAllOption
          .prop("selected", false)
          .parent()
          .selectpicker("refresh");
      } else {
        // Clicked another item, determine if all selected
        var allSelected =
          optionValues.filter(":selected").length == optionValues.length;
        selectAllOption.data("all", allSelected);
      }
    })
    .trigger("change");

  $(".selectpicker").selectpicker("refresh");

  //econtains pesquisa pelo valor exato requerido
  $.extend($.expr[":"], {
    econtains: function (obj, index, meta, stack) {
      return (
        (
          obj.textContent ||
          obj.innerText ||
          $(obj).text() ||
          ""
        ).toLowerCase() == meta[3].toLowerCase()
      );
    },
  });
}


function fecharDialogEvento(datatableDialog) {
  if (datatableDialog == true) {
    requireDataTablesDialog(true);
  }

  $(".modal").on("hidden.bs.modal", function () {
    $("#hddExecutar").val("");
  });
}

function logArrayElements(element, index, array) { }

function fecharModal(){
  $( '.modal' ).remove();
  $( '.modal-backdrop' ).remove();
  // $( 'body' ).removeClass( "modal-open" );
}

function limparModal(){
  $(".chosen").chosen("destroy");
  $(".chosen").prop("selectedindex", -1);
  $(".chosen").chosen({
    case_sensitive_search: false,
    allow_single_deselect: true,
    disable_search_threshold: 5,
    width: "100%",
  });

  $(".chosen").chosen();
}

function autoPlayVideo(url) {
  preLoadingOpen();

  var strHtml = "<table align='center'>";
  strHtml += "<tr>";
  strHtml += "<td align='center'>";
  strHtml +=
    "<iframe width='630' height='340' src='" +
    url +
    "' frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>";
  strHtml += "</td>";
  strHtml += "</tr>";
  strHtml += "</table>";

  dialogAlertVideo(strInformacao, strHtml, 3);

  setTimeout(function () {
    var maskHeight = $(document).height();
    var maskWidth = $(window).width();

    $("#mask").css({ width: maskWidth, height: maskHeight });

    $("#mask").fadeIn(1000);
    $("#mask").fadeTo("slow", 0.8);

    //Get the window height and width
    var winH = $(window).height();
    var winW = $(window).width();

    $("#modalBootstrapDialogVideo").css(
      "top",
      winH / 2 - $("#modalBootstrapDialogVideo").height() / 2
    );
    $("#modalBootstrapDialogVideo").css(
      "left",
      winW / 2 - $("#modalBootstrapDialogVideo").width() / 2
    );

    $("#modalBootstrapDialogVideo").fadeIn(2000);
    //$("#modalBootstrapDialogVideo").css('vertical-align', 'middle');

    preLoadingClose();
  }, 1000);
}

function gerarXLSDatatables() {
  $('i[class="fa fa-file-excel-o"]').trigger("click");
}

function gerarPDFDatatables() {
  $('i[class="fa fa-file-pdf-o"]').trigger("click");
}

function gerarCSVDatatables() {
  $("#cntConsulta").table2csv();
}

function carregarDataTables(
  datatable = true,
  btnNovo = true,
  btnExportar = true,
  bQtdPage = 50,
  btnTermo = false,
  bScrollX = false,
  acaoNovo = "",
  gruposEmpresasLista = false,
  ordem = 0,
  tipoordem = "desc",
  ultimaLinha = ":not(:last-child)",
  botaoNovaRota = "",
  botaNovaRotaNome = ""
) {
  $(document).ready(function () {
    preLoadingOpen();

    $("#spnTotalRegistrosConsultar").show();
    $("#spnTotalRegistrosConsultar").html(strCarregandoIcone);

    if (datatable) {
      var table = $("#cntConsulta")
        .on("init.dt", function () {
          $(".buttons-excel, .buttons-pdf").hide();

          strHtml = "";
          if (btnNovo || btnExportar) {
            if (btnNovo) {
              strRedir = "Novo";
              if ($.trim(acaoNovo) != "") {
                strRedir = $.trim(acaoNovo);
              }

              strHtml +=
                " <a class='btn btn-sm btn-success' id='btnDatatablesNovo' href='" +
                strRedir +
                "'><i class='fa fa-plus'></i> Adicionar</a>";
            }

            if (btnExportar) {
              strHtml +=
                " <a class='btn btn-sm btn-primary' id='btnExport' onClick='gerarXLSDatatables();'><i class='fa fa-print'></i> Excel</a>";
              strHtml +=
                " <a class='btn btn-sm bg-navy' onClick='gerarPDFDatatables();'><i class='fa fa-print'></i> PDF</a>";
            }

            if (btnTermo) {
              strHtml +=
                " <a class='btn btn-sm btn-warning' id='btnTermoComproTerrenoCorretor'><i class='glyphicon glyphicon-list-alt'></i> Termo</a>";
            }

            if (gruposEmpresasLista) {
              strHtml +=
                " <a class='btn btn-sm bg-navy' href='ExportarCS'><i class='fa fa-table'></i> Tabelas</a>";
            }

            if (botaoNovaRota) {
              strHtml +=
                " <a class='btn btn-sm btn-warning' href=\"javascript: void('" +
                $.trim($("#hddSigla").val()) +
                "');\" id='btnNovaRota' onClick='" +
                botaoNovaRota +
                "'><i class='fa fa-cloud-upload'></i> " +
                botaNovaRotaNome +
                "</a>";
            }
          }

          $("#spnToolbar").html(strHtml);
        })
        .DataTable({
          destroy: true,
          bPaginate: true,
          paging: true,
          responsive: true,
          lengthChange: true,
          searching: true,
          ordering: true,
          order: [[ordem, tipoordem]],
          info: true,
          autoWidth: false,
          scrollX: bScrollX,
          scrollY: "450px",
          dom: "lBfrtip",
          buttons: [
            {
              extend: "pdf",
              text: '<i class="fa fa-file-pdf-o"></i>',
              title: "Hiperdados",
              exportOptions: {
                columns: ":not(:last-child)",
                format: {
                  body: function (data, row, column, node) {
                    data = $("<p>" + data + "</p>").text();
                    return $.isNumeric(data.replace(",", "."))
                      ? data.replace(",", ".")
                      : data;
                  },
                },
              },
            },
            {
              extend: "excel",
              text: '<i class="fa fa-file-excel-o"></i>',
              title: "Hiperdados",
              exportOptions: {
                columns: ultimaLinha,
                format: {
                  body: function (data, row, column, node) {
                    data = $("<p>" + data + "</p>").text();
                    return $.isNumeric(data.replace(",", "."))
                      ? data.replace(",", ".")
                      : data;
                  },
                },
              },
            },
          ],
          lengthMenu: [10, 20, 50, 100, 200, 500],
          iDisplayLength: bQtdPage,
          language: {
            url: $.trim($("#hddFile").val()),
          },
        });

      var totalRegistros = 0;
      if ((totalRegistros = table.rows().count())) {
        setTimeout(function () {
          $("#spnTotalRegistrosConsultar").html(totalRegistros);
        }, 1000);
      } else {
        $("#spnTotalRegistrosConsultar").html("0");
      }
    } else {
      $("#spnTotalRegistrosConsultar").html("0");
    }
    preLoadingClose();
  });
}

function requireDataTables(
  bSearch = true,
  bPaginate = true,
  bOrdering = true,
  bLengthChange = true,
  bPaging = true,
  btnNovo = true,
  btnExportar = true,
  bolTexto = true
) {
  $(document).ready(function () {
    var table = $("#cntConsulta")
      .on("init.dt", function () {
        $(".buttons-excel, .buttons-pdf, .buttons-csv").hide();

        strHtml = "";
        if (btnNovo || btnExportar) {
          if (btnNovo) {
            strRedir = "Novo";
            strHtml +=
              " <a class='btn btn-sm btn-success' id='btnDatatablesNovo' href='" +
              strRedir +
              "'><i class='fa fa-plus'></i> Adicionar</a>";
          }

          if (btnExportar) {
            strHtml +=
              " <a class='btn btn-sm btn-primary' id='btnExport' onClick='gerarXLSDatatables();'><i class='fa fa-print'></i> Excel</a>";
            strHtml +=
              " <a class='btn btn-sm bg-navy' onClick='gerarPDFDatatables();'><i class='fa fa-print'></i> PDF</a>";
            strHtml +=
              " <a class='btn btn-sm' id='btnExportCSV' style='background-color:#CAE1FF;' onClick='gerarCSVDatatables();'><i class='fa fa-print'></i> CSV</a>";
          }
        }

        $("#spnToolbar").html(strHtml);
      })
      .DataTable({
        destroy: true,
        bPaginate: bPaginate,
        responsive: true,
        paging: bPaging,
        lengthChange: bLengthChange,
        searching: bSearch,
        ordering: bOrdering,
        info: true,
        autoWidth: true,
        dom: "lBfrtip",
        order: [],
        buttons: [
          {
            extend: "excel",
            text: '<i class="fa fa-file-excel-o"></i>',
            title: "Hiperdados",
            exportOptions: {
              columns: ":not(:last-child)",
              format: {
                body: function (data, row, column, node) {
                  data = $("<p>" + data + "</p>").text();
                  return $.isNumeric(data.replace(",", "."))
                    ? data.replace(",", ".")
                    : data;
                },
              },
            },
          },
          {
            extend: "pdf",
            text: '<i class="fa fa-file-pdf-o"></i>',
            title: "Hiperdados",
            exportOptions: {
              columns: ":not(:last-child)",
              format: {
                body: function (data, row, column, node) {
                  data = $("<p>" + data + "</p>").text();
                  return $.isNumeric(data.replace(",", "."))
                    ? data.replace(",", ".")
                    : data;
                },
              },
            },
          },
          {
            extend: "csv",
            filename: "Hiperdados",
            extension: ".csv",
          },
        ],
        lengthMenu: [10, 20, 50, 100, 200, 500],
        iDisplayLength: 50,
        language: {
          url: $.trim($("#hddFile").val()),
        },
      });
  });
}

function initialLoading() {
  $(document).ready(function () {
    //setTimeout(function (){
    $(window).trigger("resize");

    /*var $sidebar   = $(".sidebar-me3nu"),
        $window    = $(window),
        offset     = $sidebar.offset(),
        topPadding = 15;

      $window.scroll(function() {
        //$(".treeview-menu").hide("slow");
        if ($window.scrollTop() > offset.top) {
          $sidebar.stop().animate({
            marginTop: $window.scrollTop() - offset.top + topPadding
          });
        } else {
          $sidebar.stop().animate({
            marginTop: 0
          });
        }
      });*/
    //},1000);
  });
}

function requireDataTablesScroll(
  bSearch = true,
  bPaginate = true,
  bOrdering = true,
  bLengthChange = true,
  bPaging = true,
  btnNovo = true,
  btnExportar = true,
  parScrollY = ""
) {
  $(document).ready(function () {
    var table = $("#cntConsulta")
      .on("init.dt", function () {
        $(".buttons-excel, .buttons-pdf").hide();

        strHtml = "";
        if (btnNovo || btnExportar) {
          if (btnNovo) {
            strRedir = "Novo";
            if ($.trim(acaoNovo) != "") {
              strRedir = $.trim(acaoNovo);
            }

            strHtml +=
              " <a class='btn btn-sm btn-success' id='btnDatatablesNovo' href='" +
              strRedir +
              "'><i class='fa fa-plus'></i> Adicionar</a>";
          }

          if (btnExportar) {
            strHtml +=
              " <a class='btn btn-sm btn-primary' id='btnExport' onClick='gerarXLSDatatables();'><i class='fa fa-print'></i> Excel</a>";
            strHtml +=
              " <a class='btn btn-sm bg-navy' onClick='gerarPDFDatatables();'><i class='fa fa-print'></i> PDF</a>";
          }
        }

        $("#spnToolbar").html(strHtml);
      })
      .DataTable({
        bPaginate: bPaginate,
        responsive: true,
        paging: bPaging,
        lengthChange: bLengthChange,
        searching: bSearch,
        ordering: bOrdering,
        info: false,
        autoWidth: true,
        scrollY: parScrollY,
        scrollX: true,
        dom: "lBfrtip",
        order: [],
        buttons: [
          {
            extend: "excel",
            text: '<i class="fa fa-file-excel-o"></i>',
            title: "Hiperdados",
            exportOptions: {
              columns: ":not(:last-child)",
              format: {
                body: function (data, row, column, node) {
                  data = $("<p>" + data + "</p>").text();
                  return $.isNumeric(data.replace(",", "."))
                    ? data.replace(",", ".")
                    : data;
                },
              },
            },
          },
          {
            extend: "pdf",
            text: '<i class="fa fa-file-pdf-o"></i>',
            title: "Hiperdados",
            exportOptions: {
              columns: ":not(:last-child)",
              format: {
                body: function (data, row, column, node) {
                  data = $("<p>" + data + "</p>").text();
                  return $.isNumeric(data.replace(",", "."))
                    ? data.replace(",", ".")
                    : data;
                },
              },
            },
          },
        ],
        lengthMenu: [10, 20, 50, 100, 200, 500],
        iDisplayLength: 50,
        language: {
          url: $.trim($("#hddFile").val()),
        },
      });
  });
}

function requireDataTablesDialog(bSearch = false, bOrder = true) {
  $("#cntConsultaDialog").DataTable({
    bPaginate: true,
    responsive: true,
    paging: true,
    lengthChange: true,
    searching: bSearch,
    ordering: bOrder,
    info: true,
    autoWidth: true,
    lengthMenu: [10, 20, 50, 100, 200, 500],
    iDisplayLength: 50,
    language: {
      url: $("#hddFile").val(),
    },
  });
}

function requireDataTablesDIV(
  propriedadeDIV,
  bSearch = false,
  bOrder = true,
  paginate = false,
  totalExibir = 50
) {
  $(propriedadeDIV).DataTable({
    bPaginate: paginate,
    responsive: true,
    paging: true,
    lengthChange: true,
    searching: bSearch,
    ordering: bOrder,
    info: true,
    autoWidth: true,
    lengthMenu: [10, 20, 50, 100, 200, 500],
    iDisplayLength: totalExibir,
    language: {
      url: $("#hddFile").val(),
    },
  });
}

function preLoadingOpen() {
  $.blockUI({
    css: {
      border: "none",
      padding: "15px",
      backgroundColor: "#000",
      "-webkit-border-radius": "10px",
      "-moz-border-radius": "10px",
      opacity: 0.5,
      color: "#fff",
      baseZ: 200000000,
    },
  });
}

function preLoadingClose() {
  $.unblockUI();
}

function findValue(string, search) {
  if (string.toLowerCase().match(search.toLowerCase()) != undefined) {
    return true;
  }
  return false;
}

function excluir(acao) {
  $(document).ready(function () {
    $("#linkExcluir").html(strCarregando);

    if (
      findValue(acao, "acoes_excluir") ||
      findValue(acao, "rotas_excluir") ||
      findValue(acao, "modulos_excluir") ||
      findValue(acao, "grupos_empresas_excluir") ||
      findValue(acao, "perfis_excluir") ||
      findValue(acao, "perfis_empresas_permissoes_excluir") ||
      findValue(acao, "perfis_permissoes_excluir") ||
      findValue(acao, "mensagens_excluir") ||
      findValue(acao, "styles_item_menu_excluir") ||
      findValue(acao, "bancos_excluir") ||
      findValue(acao, "usuarios_excluir") ||
      findValue(acao, "entidades_excluir") ||
      findValue(acao, "tipos_cadastros_auxiliares_excluir") ||
      findValue(acao, "cadastros_auxiliares_excluir") ||
      findValue(acao, "corretores_excluir") ||
      findValue(acao, "terrenos_corretores_excluir") ||
      findValue(acao, "terrenos_proprietarios_excluir") ||
      findValue(acao, "terrenos_documentos_excluir") ||
      findValue(acao, "terrenos_observacoes_excluir") ||
      findValue(acao, "estudos_produtos_excluir") ||
      findValue(acao, "estudos_documentos_excluir") ||
      findValue(acao, "viabilidades_terrenos_excluir") ||
      findValue(acao, "viabilidades_vendas_excluir") ||
      findValue(acao, "viabilidades_curvas_excluir") ||
      findValue(acao, "viabilidades_periodicos_excluir") ||
      findValue(acao, "viabilidades_proporcionais_excluir") ||
      findValue(acao, "viabilidades_excluir") ||
      findValue(acao, "apoios_periodicos_excluir") ||
      findValue(acao, "terrenos_excluir") ||
      findValue(acao, "estudos_excluir") ||
      findValue(acao, "empresas_excluir") ||
      findValue(acao, "contas_bancarias_excluir") ||
      findValue(acao, "estruturas_excluir") ||
      findValue(acao, "estruturas_blocos_excluir") ||
      findValue(acao, "estruturas_unidades_excluir") ||
      findValue(acao, "viabilidades_curvas_obras_excluir") ||
      findValue(acao, "centro_custos_excluir") ||
      findValue(acao, "projetos_excluir") ||
      findValue(acao, "plano_financeiro_excluir") ||
      findValue(acao, "indexadores_excluir") ||
      findValue(acao, "indexadores_excluir_item") ||
      findValue(acao, "corretores_terrenos_excluir") ||
      findValue(acao, "corretores_excluir_terrenos") ||
      findValue(acao, "orcamentos_excluir") ||
      findValue(acao, "orcamentos_itens_excluir") ||
      findValue(acao, "insumos_excluir") ||
      findValue(acao, "itens_solicitacoes_excluir") ||
      findValue(acao, "solicitacoes_excluir") ||
      findValue(acao, "itens_apropriacoes_excluir") ||
      findValue(acao, "cotacoes_excluir") ||
      findValue(acao, "itens_cotacoes_fornecedores_excluir") ||
      findValue(acao, "pedidos_excluir") ||
      findValue(acao, "itens_1pedidos_1excluir") ||
      findValue(acao, "itens_pedidos_apropriacoes_excluir") ||
      findValue(acao, "corretores_terreno_proprietario_excluir") ||
      findValue(acao, "viabilidades_apoio_proporcionais_excluir") ||
      findValue(acao, "terrenos_tarefas_excluir") ||
      findValue(acao, "viabilidades_tabelas_vendas_excluir") ||
      findValue(acao, "pontos_excluir") ||
      findValue(acao, "contratos_item_excluir") ||
      findValue(acao, "contratos_apropriacoes_excluir") ||
      findValue(acao, "contratos_itens_medicoes_excluir") ||
      findValue(acao, "contratos_medicoes_excluir") ||
      findValue(acao, "contratos_excluir") ||
      findValue(acao, "1_documentos_excluir") ||
      findValue(acao, "contratos_anexos_remover") ||
      findValue(acao, "condicoes_serie_excluir") ||
      findValue(acao, "produtos_unidades_excluir") ||
      findValue(acao, "produtos_tabelas_excluir") ||
      findValue(acao, "comercial_imobiliarias_excluir") ||
      findValue(acao, "comercial_corretores_excluir") ||
      findValue(acao, "propostas_excluir") ||
      findValue(acao, "contas_pagar_excluir") ||
      findValue(acao, "movimentacoes_excluir") ||
      findValue(acao, "carteiras_aditivos_excluir") ||
      findValue(acao, "2_documentos_excluir_anexo") ||
      findValue(acao, "contas_pagar_anexo_excluir") ||
      findValue(acao, "carteiras_contratos_anexo_1_excluir") ||
      findValue(acao, "estruturas_anexo_2_excluir") ||
      findValue(acao, "xcv_produtos_excluir") ||
      findValue(acao, "hiperdados_cidades_excluir") ||
      findValue(acao, "hiperdados_bairros_excluir") ||
      findValue(acao, "hiperdados_inconporadoras_excluir") ||
      findValue(acao, "hiperdados_construtoras_excluir") ||
      findValue(acao, "hiperdados_empreendimentos_unidades_excluir") ||
      findValue(acao, "hiperdados_empreendimentos_excluir") ||
      findValue(acao, "hiperdados_empreendimentos_incorporadoras_excluir") ||
      findValue(acao, "hiperdados_empreendimentos_construtoras_excluir") ||
      findValue(acao, "hiperdados_empreendimentos_vendedores_excluir") ||
      findValue(acao, "hiperdados_vendedores_excluir") ||
      findValue(acao, "hiperdados_empreendimentos_plantas_excluir") ||
      findValue(acao, "hiperdados_empreendimentos_tabelas_excluir") ||
      findValue(acao, "1_carteiras2_contratos3_excluir")
    ) {
      $.post(
        acao,
        {
          ROT_ID2: $.trim($("#ROT_ID2").val()),
          arrParametros: $("#hddExcluirParametros").val(),
          valor: "1",
        },
        function (data) {
          //alert(data); return;
          if (data.sucesso == "true") {
            $("#hddExcluir").val("");
            $("#descricaoExcluir").html("");
            $("#linkExcluir").html("Ok");
            $(".modal-backdrop").hide();

            if (findValue(acao, "terrenos_corretores_excluir")) {
              $.notify(data.mensagem, "success");
              consultarTerrenosCorretores();
              return;
            } else if (findValue(acao, "terrenos_proprietarios_excluir")) {
              $.notify(data.mensagem, "success");
              consultarTerrenosProprietarios();
              return;
            } else if (findValue(acao, "terrenos_documentos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarTerrenosDocumentos();
              return;
            } else if (findValue(acao, "terrenos_observacoes_excluir")) {
              $.notify(data.mensagem, "success");
              consultarTerrenosObservacoes();
              return;
            } else if (findValue(acao, "estudos_produtos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarConcorrentes($("#EST_ID").val());
              return;
            } else if (findValue(acao, "estudos_documentos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarEstudosDocumentos($("#EST_ID").val());
              return;
            } else if (findValue(acao, "viabilidades_terrenos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarViabilidadeTerreno();
              return;
            } else if (findValue(acao, "viabilidades_vendas_excluir")) {
              $.notify(data.mensagem, "success");
              consultarViabilidadeVendas();
              return;
            } else if (findValue(acao, "viabilidades_curvas_excluir")) {
              $.notify(data.mensagem, "success");
              consultarViabilidadeCurvas();
              return;
            } else if (findValue(acao, "viabilidades_periodicos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarViabilidadesPeriodicos();
              return;
            } else if (findValue(acao, "viabilidades_proporcionais_excluir")) {
              $.notify(data.mensagem, "success");
              consultarViabilidadesProporcionais();
              return;
            } else if (findValue(acao, "estruturas_blocos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarEstuturasBloco();
              return;
            } else if (findValue(acao, "estruturas_unidades_excluir")) {
              $.notify(data.mensagem, "success");
              consultarEstuturasBloco();
              return;
            } else if (findValue(acao, "indexadores_excluir_item")) {
              $.notify(data.mensagem, "success");
              consultarValoresIndexadores();
              return;
            } else if (findValue(acao, "corretores_terrenos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarTerrenosCorretoresDocumentos();
              return;
            } else if (findValue(acao, "orcamentos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarOrcamentos();
              return;
            } else if (findValue(acao, "orcamentos_itens_excluir")) {
              $.notify(data.mensagem, "success");
              consultarItensOrcamentos($("#ORC_ID").val());
              return;
            } else if (findValue(acao, "itens_solicitacoes_excluir")) {
              $.notify(data.mensagem, "success");
              consultarItensSolicitacoes($("#SOL_ID").val());
              return;
            } else if (findValue(acao, "solicitacoes_excluir")) {
              $.notify(data.mensagem, "success");
              consultarSolicitacoes();
              return;
            } else if (findValue(acao, "itens_apropriacoes_excluir")) {
              $.notify(data.mensagem, "success");
              consultarItemApropriacoes(data.codigo);
              $("#SOA_PercentualTotal").val(data.total);
              return;
            } else if (findValue(acao, "cotacoes_excluir")) {
              $.notify(data.mensagem, "success");
              consultarCotacoes();
              return;
            } else if (findValue(acao, "itens_cotacoes_fornecedores_excluir")) {
              $.notify(data.mensagem, "success");
              consultarItensCotacoes($("#COT_ID").val());
              return;
            } else if (findValue(acao, "pedidos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarPedidos();
              return;
            } else if (findValue(acao, "itens_1pedidos_1excluir")) {
              $.notify(data.mensagem, "success");
              consultarItensPedidos($("#PED_ID").val());
              return;
            } else if (findValue(acao, "itens_pedidos_apropriacoes_excluir")) {
              $.notify(data.mensagem, "success");
              consultarItemPedidoApropriacoes();
              return;
            } else if (
              findValue(acao, "corretores_terreno_proprietario_excluir")
            ) {
              $.notify(data.mensagem, "success");
              consultarTerrenosProprietariosCorretores();
              return;
            } else if (findValue(acao, "terrenos_tarefas_excluir")) {
              $.notify(data.mensagem, "success");
              consultarTerrenosTarefas();
              return;
            } else if (findValue(acao, "contratos_apropriacoes_excluir")) {
              $.notify(data.mensagem, "success");
              consultarItemContratoApropriacoes();
              return;
            } else if (findValue(acao, "contratos_itens_medicoes_excluir")) {
              $.notify(data.mensagem, "success");
              consultarItensMedicoes($("#CON_ID").val());
              return;
            } else if (findValue(acao, "contratos_medicoes_excluir")) {
              $.notify(data.mensagem, "success");
              consultarMedicoes();
              return;
            } else if (findValue(acao, "contratos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarContratos();
              return;
            } else if (findValue(acao, "produtos_unidades_excluir")) {
              $.notify(data.mensagem, "success");
              consultarUnidadesProdutos();
              return;
            } else if (findValue(acao, "produtos_tabelas_excluir")) {
              $.notify(data.mensagem, "success");
              consultarTabelasProdutos();
              return;
            } else if (findValue(acao, "contratos_item_excluir")) {
              $.notify(data.mensagem, "success");
              consultarItensContratos($("#CON_ID").val());
              return;
            } else if (findValue(acao, "1_documentos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarDocumentos();
              return;
            } else if (findValue(acao, "propostas_excluir")) {
              $.notify(data.mensagem, "success");
              consultarPropostas();
              return;
            } else if (findValue(acao, "contas_pagar_excluir")) {
              $.notify(data.mensagem, "success");
              consultarContasPagar();
              return;
            } else if (findValue(acao, "movimentacoes_excluir")) {
              $.notify(data.mensagem, "success");
              consultarMovimentacoes();
              return;
            } else if (findValue(acao, "carteiras_aditivos_excluir")) {
              $.notify(data.mensagem, "success");
              consultarCarteiraAditivos();
              return;
            } else if (findValue(acao, "2_documentos_excluir_anexo")) {
              $.notify(data.mensagem, "success");
              consultarComprasDocumentosAnexos();
              return;
            } else if (findValue(acao, "contas_pagar_anexo_excluir")) {
              $.notify(data.mensagem, "success");
              consultarFinanceiroContasPagarAnexos();
              return;
            } else if (findValue(acao, "carteiras_contratos_anexo_1_excluir")) {
              $.notify(data.mensagem, "success");
              consultarCarteiraContratosAnexos();
              return;
            } else if (findValue(acao, "estruturas_anexo_2_excluir")) {
              $.notify(data.mensagem, "success");
              consultarEmpreendimentosEstruturasAnexos();
              return;
            } else if (
              findValue(acao, "hiperdados_empreendimentos_unidades_excluir")
            ) {
              $.notify(data.mensagem, "success");
              consultarHiperdadosEmpreendimentosUnidades();
              return;
            } else if (
              findValue(
                acao,
                "hiperdados_empreendimentos_incorporadoras_excluir"
              )
            ) {
              $.notify(data.mensagem, "success");
              consultarHiperdadosEmpreendimentosIncorporadoras();
              return;
            } else if (
              findValue(acao, "hiperdados_empreendimentos_construtoras_excluir")
            ) {
              $.notify(data.mensagem, "success");
              consultarHiperdadosEmpreendimentosConstrutoras();
              return;
            } else if (
              findValue(acao, "hiperdados_empreendimentos_vendedores_excluir")
            ) {
              $.notify(data.mensagem, "success");
              consultarHiperdadosEmpreendimentosVendedoras();
              $("#spnDataHoraUltimaAtualizacao").html(data.strDataAtual);
              return;
            } else if (
              findValue(acao, "hiperdados_empreendimentos_plantas_excluir")
            ) {
              $.notify(data.mensagem, "success");
              consultarHiperdadosEmpreendimentosPlantas();
              return;
            } else if (
              findValue(acao, "hiperdados_empreendimentos_tabelas_excluir")
            ) {
              $.notify(data.mensagem, "success");
              consultarHiperdadosEmpreendimentosTabelas();
              return;
            } else if (findValue(acao, "1_carteiras2_contratos3_excluir")) {
              $.notify(data.mensagem, "success");
              consultarContratosCarteiras();
              return;
            } else {
              redir(data.redir, "parent");
              return;
            }
          } else {
            $("#linkExcluir").html(strConfirmarOK);
            $.notify(data.mensagem, "warn");
            return;
          }
        },
        "json"
      );
    } else {
      redir($("#hddHome").val(), "parent");
      return;
    }
  });
}

function mouseOut(cor, linha) {
  linha.style.background = cor;
  linha.style.color = "#000000";
}

function mouseOver(cor, linha, ident) {
  linha.style.background = cor;
  linha.style.color = "#FFFFFF";
  //linha.style.color;
}

function validacaoExibirBtnSalvarUsuarios() {
  //Verifica se Ã© modo de INCLUSÃO ou ATUALIZAÇÃO
  if ($.trim($("#USU_ID").val()) == "") {
    if ($("#grp-mail").attr("class").includes("has-success")) {
      $("#btnSalvar").prop("disabled", false);
    } else if (
      $("#grp-pass").attr("class").includes("has-success") &&
      $("#grp-pass2").attr("class").includes("has-success")
    ) {
      $("#btnSalvar").prop("disabled", true);
    }
  } else {
    if ($("#grp-mail").attr("class").includes("has-success")) {
      $("#btnSalvar").prop("disabled", false);
    } else {
      $("#btnSalvar").prop("disabled", true);
    }
  }
}

function validacaoExibirBtnSalvarUsuariosRapido() {
  if ($("#USU_AtualizarSenha").prop("checked") == true) {
    if (
      $("#grp-mailRapido").attr("class").includes("has-success") &&
      $("#grp-passRapido").attr("class").includes("has-success") &&
      $("#grp-pass2Rapido").attr("class").includes("has-success")
    ) {
      $("#btnSalvarRapido").prop("disabled", false);
    } else {
      $("#btnSalvarRapido").prop("disabled", true);
    }
  } else {
    if ($("#grp-mailRapido").attr("class").includes("has-success")) {
      $("#btnSalvarRapido").prop("disabled", false);
    } else {
      $("#btnSalvarRapido").prop("disabled", true);
    }
  }
}

function checarSenhasPadrao(strSenha, strSenha2) {
  $("#btnSalvar").prop("disabled", true);

  if ($.trim(strSenha) != "" && $.trim(strSenha2) != "") {
    if (strSenha != strSenha2) {
      $("#lbl-pass").removeClass("has-success").addClass("has-error");
      $("#grp-pass").removeClass("has-success").addClass("has-error");
      $("#lbl-pass2").removeClass("has-success").addClass("has-error");
      $("#grp-pass2").removeClass("has-success").addClass("has-error");

      $.notify("Senhas precisam ser iguais.", "warn");
    } else {
      $("#lbl-pass").removeClass("has-error").addClass("has-success");
      $("#grp-pass").removeClass("has-error").addClass("has-success");
      $("#lbl-pass2").removeClass("has-error").addClass("has-success");
      $("#grp-pass2").removeClass("has-error").addClass("has-success");

      $("#btnSalvar").prop("disabled", false);
    }
  }
}

function checarSenhas(strSenha, strSenha2) {
  if ($.trim(strSenha) != "" && $.trim(strSenha2) != "") {
    if (strSenha != strSenha2) {
      $("#lbl-pass").removeClass("has-success").addClass("has-error");
      $("#grp-pass").removeClass("has-success").addClass("has-error");
      $("#lbl-pass2").removeClass("has-success").addClass("has-error");
      $("#grp-pass2").removeClass("has-success").addClass("has-error");

      $.notify("Senhas precisam ser iguais.", "warn");
    } else {
      $("#lbl-pass").removeClass("has-error").addClass("has-success");
      $("#grp-pass").removeClass("has-error").addClass("has-success");
      $("#lbl-pass2").removeClass("has-error").addClass("has-success");
      $("#grp-pass2").removeClass("has-error").addClass("has-success");
    }
  }

  validacaoExibirBtnSalvarUsuarios();
}

var tableToExcel = (function () {
  var uri = "data:application/vnd.ms-excel;base64,",
    template =
      '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table>{table}</table></body></html>',
    base64 = function (s) {
      return window.btoa(unescape(encodeURIComponent(s)));
    },
    format = function (s, c) {
      return s.replace(/{(\w+)}/g, function (m, p) {
        return c[p];
      });
    };
  return function (table, name) {
    if (!table.nodeType) table = document.getElementById(table);
    var ctx = { worksheet: name || "Worksheet", table: table.innerHTML };
    window.location.href = uri + base64(format(template, ctx));
  };
})();

function adicionarFormularioAcao(checkboxID, rotaID, acaoID) {
  var strAcao = "Excluir";
  if ($("#" + checkboxID).prop("checked")) strAcao = "Adicionar";

  $.post(
    $.trim($("#hddAdicionarRemoverFormularioAcao").val()),
    {
      ACO_ID: acaoID,
      ROT_ID: rotaID,
      ROT_ID2: $.trim($("#ROT_ID2").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
      }
    },
    "json"
  );
}

function formatarCPFCNPJ(valor) {
  if (valor.length == 11) {
    return (
      valor.substring(0, 3) +
      "." +
      valor.substring(3, 6) +
      "." +
      valor.substring(6, 9) +
      "-" +
      valor.substring(9, 11)
    );
  } else if (valor.length == 14) {
    return (
      valor.substring(0, 2) +
      "." +
      valor.substring(2, 5) +
      "." +
      valor.substring(5, 8) +
      "/" +
      valor.substring(8, 12) +
      "-" +
      valor.substring(12, 14)
    );
  }

  return false;
}

function textoParaFloat(texto, mascara) {
  // Retira pontos que separam milhar se existirem. Ex: 1.000,25 vira 1000,25
  texto = texto.replace(".", "");

  // Substitui vírgula separando a casa decimal por ponto ex: 1000,25 vira 1000.25
  texto = texto.replace(",", "."); // isso é necessário para converter para float corretamente

  return parseFloat(texto, mascara); // Retorna um número float para ser usado para fazer cálculos
}

function floatParaTexto(numero, mascara = 2) {
  numero = numero.toFixed(mascara).split(".");
  numero[0] = numero[0].split(/(?=(?:...)*$)/).join(".");
  return numero.join(",");
}

function toggleGroup(type) {
  for (var i = 0; i < markerGroups[type].length; i++) {
    var marker = markerGroups[type][i];
    if (!marker.getVisible()) {
      marker.setVisible(true);
    } else {
      marker.setVisible(false);
    }
  }
}

function adicionarFormularioModulo(checkboxID, moduloID, rotaID) {
  var strAcao = "Excluir";
  if ($("#" + checkboxID).prop("checked")) {
    strAcao = "Adicionar";
  }

  $.post(
    $.trim($("#hddAdicionarRemoverFormulariosModulos").val()),
    {
      MOD_ID: moduloID,
      ROT_ID: rotaID,
      ROT_ID2: $.trim($("#ROT_ID2").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        //$.notify(data.mensagem, "success");
      }
    },
    "json"
  );
}

function adicionarPerfilPermissoes(chk, perfilID, acaoID, clicouTodos) {
  /*
  var strAcao 			= 'Excluir';
  var arrFormulariosAcoes = new Array();
  if ($('#'+chk.id).prop('checked')) strAcao = 'Adicionar';

  if ($.trim(clicouTodos) != ''){
    if (strAcao == 'Excluir'){
      $('.cssAcaoForm_'+acaoID).prop('checked', false);
    }else{
      $('.cssAcaoForm_'+acaoID).prop('checked', true);
    }

    $("input[type=checkbox][name='chkaco_"+acaoID+"[]']:checked").each(function(){
      arrFormulariosAcoes.push($(this).val());
    });
  }else{
    arrFormulariosAcoes.push($('#'+chk.id).val());
  }

  $.post($.trim($('#hddAcaoPermissoesSalvar').val()),
  {
    PER_ID: perfilID,
    ROT_ID: arrFormulariosAcoes,
    ROT_ID2: $.trim($('#ROT_ID2').val()),
    ACO_ID: acaoID,
    strAcao: strAcao
  },
  function(data){
    //alert(data); return;
    if(data.sucesso == 'true'){

    }
    }, 'json'
  );
  */
}

function carregarTela(caminho) {
  redir(caminho);
}

function mLibMarcar(x) {
  var strChk = "id" + x;
  var strCel = "linha" + x;
  var strCor = $("#" + strCel).css("background-color");
  var strCel2 = jQuery("#" + strCel);

  if ($("#" + strChk).is(":checked")) {
    $("#" + strCel).css("background-color", "#FFF868");
  } else {
    $("#" + strCel).css("background-color", strCor);
  }
}

function enterPesquisarEndereco(e) {
  if (e.keyCode == 13) {
    searchAddress();
  }
}

function formatarMoeda(valor, casas, separdor_decimal, separador_milhar) {
  var valor_total = parseInt(valor * Math.pow(10, casas));
  var inteiros = parseInt(
    parseInt(valor * Math.pow(10, casas)) / parseFloat(Math.pow(10, casas))
  );
  var centavos = parseInt(
    parseInt(valor * Math.pow(10, casas)) % parseFloat(Math.pow(10, casas))
  );

  if (centavos % 10 == 0 && centavos + "".length < 2) {
    centavos = centavos + "0";
  } else if (centavos < 10) {
    centavos = "0" + centavos;
  }

  var milhares = parseInt(inteiros / 1000);
  inteiros = inteiros % 1000;
  var retorno = "";

  if (milhares > 0) {
    retorno = milhares + "" + separador_milhar + "" + retorno;
    if (inteiros == 0) {
      inteiros = "000";
    } else if (inteiros < 10) {
      inteiros = "00" + inteiros;
    } else if (inteiros < 100) {
      inteiros = "0" + inteiros;
    }
  }
  retorno += inteiros + "" + separdor_decimal + "" + centavos;

  return retorno;
}

function desabilitarBotao(strBotao) {
  $("#" + strBotao).attr("disabled", true);
  $("#" + strBotao).val("Carregando...");
  $("#" + strBotao).css("color", "#9b978f");
}

function habilitarBotao(strBotao, strValorBotao) {
  if (strValorBotao == "") strValorBotao = "Salvar";
  $("#" + strBotao).attr("disabled", false);
  $("#" + strBotao).val(strValorBotao);
  $("#" + strBotao).css({ color: "#FFFFFF" });
}

function redir(url, tipo) {
  if (tipo == "parent") {
    parent.location = url;
  } else {
    document.location.href = url;
  }
}

function dialogAlertConfirmarTermoComproTerreno(
  grupoID,
  strTitulo,
  strMensagem
) {
  BootstrapDialog.show({
    id: "modalBootstrapDialog",
    size: BootstrapDialog.SIZE_WIDE,
    type: BootstrapDialog.TYPE_PRIMARY,
    title: $.trim(strTitulo),
    message: $.trim(strMensagem),
    buttons: [
      {
        label: "<i class='glyphicon glyphicon-minus-sign'></i> Não",
        cssClass: "btn-danger",
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        id: "btnConfirmarDialogAlert",
        label: "<i class='glyphicon glyphicon-flag'></i> Confirma o Termo?",
        cssClass: "btn-success",
        action: function (dialogItself) {
          preLoadingOpen();

          $.post(
            $.trim($("#hddComproTerrenoCorretorTermoConfirmar").val()),
            {
              GRE_ID: $.trim(grupoID),
            },
            function (data2) {
              //alert(data2); return;
              if (data2.sucesso == "true") {
                $.notify(data2.mensagem, "success");

                setTimeout(function () {
                  redir("", "parent");
                }, 1500);
              } else {
                $.notify(data2.mensagem, "error");
              }
              preLoadingClose();
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function dialogAlertAtualizar(strTitulo, strMensagem, strTipo) {
  var types = BootstrapDialog.TYPE_DEFAULT;
  if (strTipo == 2) {
    types = BootstrapDialog.TYPE_INFO;
  } else if (strTipo == 3) {
    types = BootstrapDialog.TYPE_PRIMARY;
  } else if (strTipo == 4) {
    types = BootstrapDialog.TYPE_SUCCESS;
  } else if (strTipo == 5) {
    types = BootstrapDialog.TYPE_WARNING;
  } else if (strTipo == 6) {
    types = BootstrapDialog.TYPE_DANGER;
  }

  BootstrapDialog.show({
    id: "modalBootstrapDialog",
    size: BootstrapDialog.SIZE_WIDE,
    type: types,
    title: $.trim(strTitulo),
    message: $.trim(strMensagem),
    buttons: [
      {
        id: "btnAtualizarDialogAlert",
        label: "<i class='glyphicon glyphicon-refresh'></i> Atualizar",
        cssClass: "btn-primary",
      },
    ],
  });
}

function dialogAlertUsuarios(strTitulo, strMensagem, strTipo) {
  var types = BootstrapDialog.TYPE_DEFAULT;
  if (strTipo == 2) {
    types = BootstrapDialog.TYPE_INFO;
  } else if (strTipo == 3) {
    types = BootstrapDialog.TYPE_PRIMARY;
  } else if (strTipo == 4) {
    types = BootstrapDialog.TYPE_SUCCESS;
  } else if (strTipo == 5) {
    types = BootstrapDialog.TYPE_WARNING;
  } else if (strTipo == 6) {
    types = BootstrapDialog.TYPE_DANGER;
  }

  BootstrapDialog.show({
    id: "modalBootstrapDialog",
    size: BootstrapDialog.SIZE_WIDE,
    type: types,
    title: $.trim(strTitulo),
    message: $.trim(strMensagem),
  });
}

function dialogAlert(strTitulo, strMensagem, strTipo, strRedir, init = true){
  var types = BootstrapDialog.TYPE_DEFAULT;
  if (strTipo == 2) {
    types = BootstrapDialog.TYPE_INFO;
  } else if (strTipo == 3) {
    types = BootstrapDialog.TYPE_PRIMARY;
  } else if (strTipo == 4) {
    types = BootstrapDialog.TYPE_SUCCESS;
  } else if (strTipo == 5) {
    types = BootstrapDialog.TYPE_WARNING;
  } else if (strTipo == 6) {
    types = BootstrapDialog.TYPE_DANGER;
  }

  BootstrapDialog.show({
    id: "modalBootstrapDialog",
    size: BootstrapDialog.SIZE_WIDE,
    type: types,
    title: $.trim(strTitulo),
    message: $.trim(strMensagem),
    buttons: [
      {
        id: "btnCloseDialogAlert",
        label: "Fechar",
        action: function (dialogItself) {
          dialogItself.close();
          if ($.trim(strRedir) != "") {
            redir(strRedir, "parent");
          }
        },
      },
    ],
  });

  if (init) {
    setTimeout(function () {
      setInitFunctions();
    }, 1500);
  }
}

function dialogAlertVideo(strTitulo, strMensagem, strTipo) {
  var types = BootstrapDialog.TYPE_DEFAULT;
  if (strTipo == 2) {
    types = BootstrapDialog.TYPE_INFO;
  } else if (strTipo == 3) {
    types = BootstrapDialog.TYPE_PRIMARY;
  } else if (strTipo == 4) {
    types = BootstrapDialog.TYPE_SUCCESS;
  } else if (strTipo == 5) {
    types = BootstrapDialog.TYPE_WARNING;
  } else if (strTipo == 6) {
    types = BootstrapDialog.TYPE_DANGER;
  }

  BootstrapDialog.show({
    id: "modalBootstrapDialogVideo",
    size: BootstrapDialog.SIZE_WIDE,
    type: types,
    title: $.trim(strTitulo),
    message: $.trim(strMensagem),
  });
}

function dialogAlert2(
  strTitulo,
  strMensagem,
  strTipo,
  htmlID = "",
  closer = true,
  init = true
) {
  var types = BootstrapDialog.TYPE_DEFAULT;
  if (strTipo == 2) {
    types = BootstrapDialog.TYPE_INFO;
  } else if (strTipo == 3) {
    types = BootstrapDialog.TYPE_PRIMARY;
  } else if (strTipo == 4) {
    types = BootstrapDialog.TYPE_SUCCESS;
  } else if (strTipo == 5) {
    types = BootstrapDialog.TYPE_WARNING;
  } else if (strTipo == 6) {
    types = BootstrapDialog.TYPE_DANGER;
  }

  if ($.trim(htmlID) != "") {
    if (!($("#" + htmlID).data("bs.modal") || {}).isShown) {
      BootstrapDialog.show({
        type: types,
        id: $.trim(htmlID),
        size: BootstrapDialog.SIZE_WIDE,
        title: $.trim(strTitulo),
        message: $.trim(strMensagem),
        closable: closer,
        buttons: [
          {
            id: "btnCloseDialogAlert",
            label: "Fechar",
            action: function (dialogItself) {
              dialogItself.close();
            },
          },
        ],
      });
    }
  } else {
    BootstrapDialog.show({
      type: types,
      size: BootstrapDialog.SIZE_WIDE,
      title: $.trim(strTitulo),
      message: $.trim(strMensagem),
      closable: closer,
      buttons: [
        {
          id: "btnCloseDialogAlert",
          label: "Fechar",
          action: function (dialogItself) {
            dialogItself.close();
          },
        },
      ],
    });
  }

  if (init == true) {
    setTimeout(function () {
      setInitFunctions();
    }, 1500);
  }
}

function dialogAlertName(dialogID, strTitulo, strMensagem, strTipo) {
  var types = BootstrapDialog.TYPE_DEFAULT;
  if (strTipo == 2) {
    types = BootstrapDialog.TYPE_INFO;
  } else if (strTipo == 3) {
    types = BootstrapDialog.TYPE_PRIMARY;
  } else if (strTipo == 4) {
    types = BootstrapDialog.TYPE_SUCCESS;
  } else if (strTipo == 5) {
    types = BootstrapDialog.TYPE_WARNING;
  } else if (strTipo == 6) {
    types = BootstrapDialog.TYPE_DANGER;
  }

  $("#" + dialogID)
    .closest(".ui-dialog-content")
    .dialog("destroy");

  BootstrapDialog.show({
    size: BootstrapDialog.SIZE_WIDE,
    id: dialogID,
    type: types,
    title: $.trim(strTitulo),
    message: $.trim(strMensagem),
  });
}

function soNumeros(e, args) {
  // Funcao que permite apenas teclas numï¿½ricas e
  // todos os caracteres que estiverem na lista
  // de argumentos.
  // Deve ser chamada no evento onKeyPress desta forma
  //  onKeyPress="return (soNumeros(event,'0'));"
  // caso queira apenas permitir caracters como por exemplo um campo que sï¿½ aceite valores em Hexadecimal (de 0 a F) usamos
  //  onKeyPress ="return (soNumeros(event,'AaBbCcDdEeFf'));"

  /* Esta parte comentada ï¿½ a que testei exaustivamente e garanto que funciona em praticamente todos os browsers
        var evt='';// devido a um warning gerado pelo Console de Javascript que "enxergava" uma redeclaracao de "evt" decidi declara-la uma vez e alterar ser valor posteriormente

        if (document.all){evt=event.keyCode;} // caso seja IE
        else{evt = e.charCode;}    // do contrario deve ser Mozilla
O cï¿½digo a seguir teste apenas em FireFox e Internet Explorer 6 e funcionou perfeitamente. Caso vc tenha algum problema com esta funcao por favor entre em contato
*/
  var evt = e.keyCode ? e.keyCode : e.charCode;
  var chr = String.fromCharCode(evt); // pegando a tecla digitada;
  // Se o cï¿½digo for menor que 20 ï¿½ porque deve ser caracteres de controle
  // ex.: <ENTER>, <TAB>, <BACKSPACE> portanto devemos permitir
  // as teclas numï¿½ricas vao de 48 a 57
  return evt < 20 || (evt > 47 && evt < 58) || args.indexOf(chr) > -1;
}

function marcardesmarcar() {
  var bolChecked = false;
  $(".marcar").each(function () {
    if (!this.checked) {
      bolChecked = true;
    }
  });

  if (bolChecked) {
    $(".btnProgramar").show();
    $(".marcar").prop("checked", true);
  } else {
    $(".marcar").prop("checked", false);
    $(".btnProgramar").hide();
  }
}

function desabilitarClassDiferente(input, strCSS) {
  if (input.checked) {
    $('input[name^="items[]"]')
      .not("." + strCSS)
      .prop("disabled", true);
    $(".btnProgramar").show();
  } else {
    $('input[name^="items[]"]').prop("disabled", false);
    $(".btnProgramar").hide();
  }
}

function marcardesmarcarItem(check) {
  var bolChecked = false;
  $(".marcar").each(function () {
    if (this.checked) {
      bolChecked = true;
    }
  });

  if ($("#" + check).prop("checked") == true || bolChecked) {
    $(".btnProgramar").show();
  } else {
    $(".btnProgramar").hide();
  }
}

function checarBotaoSelecionar() {
  $("#btnSelecionar").prop("disabled", true);

  if ($("#chkTodos").is(":checked")) {
    $("#btnSelecionar").prop("disabled", false);
  }
}

function marcarDescarmarTodosCheckbox(cssID) {
  $("." + cssID).each(function () {
    if (this.checked) this.checked = false;
    else this.checked = true;
  });
}

function marcarDesmarcarReajustes(chkTodosID, cssID) {
  if ($("#" + chkTodosID).is(":checked")) {
    $("." + cssID).each(function () {
      this.checked = true;
    });
    $("#btnCarteirasReajustes").show();
  } else {
    $("." + cssID).each(function () {
      this.checked = false;
    });

    $("#btnCarteirasReajustes").hide();
  }
}

function proximo(e, campo) {
  if (e.keyCode == 13) {
    $("#" + campo).focus();
  }
}

function isValidDate(dateString) {
  // First check for the pattern
  if (!/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(dateString)) return false;

  // Parse the date parts to integers
  var parts = dateString.split("/");
  var day = parseInt(parts[1], 10);
  var month = parseInt(parts[0], 10);
  var year = parseInt(parts[2], 10);

  // Check the ranges of month and year
  if (year < 1000 || year > 3000 || month == 0 || month > 12) return false;

  var monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

  // Adjust for leap years
  if (year % 400 == 0 || (year % 100 != 0 && year % 4 == 0))
    monthLength[1] = 29;

  // Check the range of the day
  return day > 0 && day <= monthLength[month - 1];
}

function carregarRotas() {
  $("#ulFormularios").html(strCarregandoCor);

  $.post(
    $.trim($("#hddCarregarFormularios").val()),
    { ROT_Pesquisar: $.trim($("#txtPesquisarFormularios").val()) },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $(document).ready(function () {
          $("#ulFormularios").html(data.strHtml);
          var url = data.url_projeto + data.explodir[0]; //window.location;

          if (
            $.trim(data.explodir[0]) !== "Usuarios" ||
            $.trim(data.explodir[1]) !== "Home"
          ) {
            // for sidebar menu but not for treeview submenu
            $("ul.sidebar-menu a")
              .filter(function () {
                var validar = this.href == window.location;
                if (validar) return validar;
                return (
                  this.href == url + "/Filtrar" ||
                  this.href == url + "/Consultar"
                );
              })
              .parent()
              .siblings()
              .removeClass("active")
              .end()
              .addClass("active");

            // for treeview which is like a submenu
            $("ul.treeview-menu a")
              .filter(function () {
                var validar = this.href == window.location;
                if (validar) return validar;
                return (
                  this.href == url + "/Filtrar" ||
                  this.href == url + "/Consultar"
                );
              })
              .parentsUntil(".sidebar-menu > .treeview-menu")
              .siblings()
              .removeClass("active menu-open")
              .end()
              .addClass("active menu-open");
          }
        });
      }
    },
    "json"
  );
}

function checarUsuarios() {
  $("#USU_Email").trigger("blur");
  validacaoExibirBtnSalvarUsuarios();
}

function carregarGraficosUsuarios() {
  $.post(
    $.trim($("#hddCarregarGraficoUsuarios").val()),
    { USU_Ano: $.trim($("#hddAnoAtual").val()) },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        Highcharts.chart("chart-user", {
          chart: {
            type: "column",
          },
          title: {
            text:
              $.trim($("#hddLabelGraficoUsuarios").val()) +
              " (" +
              $.trim($("#hddAnoAtual").val()) +
              ")",
          },
          xAxis: {
            categories: data.arrCategories,
            crosshair: true,
          },
          yAxis: {
            min: 0,
            title: {
              text: $.trim($("#hddTituloPagina").val()),
            },
          },
          tooltip: {
            headerFormat:
              '<span style="font-size:10px">{point.key}/' +
              $.trim($("#hddAnoAtual").val()) +
              "</span><table>",
            pointFormat:
              '<tr><td style="color:{series.color};padding:0">Quantidade: </td>' +
              '<td style="padding:0"><b>{point.y}</b></td></tr>',
            footerFormat: "</table>",
            shared: true,
            useHTML: true,
          },
          plotOptions: {
            column: {
              pointPadding: 0.2,
              borderWidth: 0,
            },
          },
          series: [
            {
              name: $.trim($("#hddSigla").val()),
              data: data.arrDatas,
            },
          ],
        });
      }
    },
    "json"
  );
}

function carregarGraficosTerrenosCorretores() {
  $.post(
    $.trim($("#hddCarregarGraficoTerrenosCorretores").val()),
    { COR_Ano: $.trim($("#hddAnoAtual").val()) },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        Highcharts.chart("chart-user", {
          chart: {
            type: "column",
          },
          lang: {
            decimalPoint: ",",
          },
          title: {
            text:
              $.trim($("#hddLabelGraficoTerrenosCorretores").val()) +
              " (" +
              $.trim($("#hddAnoAtual").val()) +
              ")",
          },
          xAxis: {
            categories: data.arrCategories,
            crosshair: true,
          },
          yAxis: {
            min: 0,
            title: {
              text: $.trim($("#hddTituloPagina").val()),
            },
          },
          tooltip: {
            headerFormat:
              '<span style="font-size:10px">{point.key}/' +
              $.trim($("#hddAnoAtual").val()) +
              "</span><table>",
            pointFormat:
              '<tr><td style="color:{series.color};padding:0">Quantidade: </td>' +
              '<td style="padding:0"><b>{point.y}</b></td></tr>',
            footerFormat: "</table>",
            shared: true,
            useHTML: true,
          },
          plotOptions: {
            column: {
              pointPadding: 0.2,
              borderWidth: 0,
            },
          },
          series: [
            {
              name: $.trim($("#hddSigla").val()),
              data: data.arrDatas,
            },
          ],
        });
      }
    },
    "json"
  );
}

function validarBotaoSalvarEntidade() {
  $("#btnSalvar").prop("disabled", true);

  var bolSelecionou = false;
  if ($("#TPE_ID option:selected").length > 0) {
    bolSelecionou = true;
  }

  if (
    bolSelecionou == true &&
    $("#lblCPFCNPJ").attr("class").includes("has-success") &&
    $("#grp-cpfcnpj").attr("class").includes("has-success") &&
    $("#ENT_CPFCNPJ").attr("class").includes("has-success")
  ) {
    $("#btnSalvar").prop("disabled", false);
  }
}

//Exibir dialog com as ações para adicionar/remover ao formulário;
function consultarRotasAcoes(rotaID) {
  $.post(
    $.trim($("#hddAcaoConsultarDados").val()),
    { ROT_ID: rotaID },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          strHtml =
            "<table class='table table-striped table-hover table-condensed'>";
          strHtml += "<thead>";
          strHtml += "<tr class='bg-primary'>";
          strHtml += "<th align='left'>Descrição</th>";
          strHtml += "<th align='center'></th>";
          strHtml += "</tr>";
          strHtml += "</thead>";
          strHtml += "<tbody>";

          var idCheck = "";
          for (var i = 0; i < data.arrDados.length; i++) {
            idCheck = "chk_" + i;

            strHtml += "<tr>";
            strHtml +=
              "<td align='left'>" + data.arrDados[i].ACO_Descricao + "</td>";
            strHtml += "<td align='center'>";
            strHtml +=
              "<input type='checkbox' " +
              data.strChecked[data.arrDados[i].ACO_ID] +
              " id='" +
              idCheck +
              "' value=" +
              data.arrDados[i].ACO_ID +
              " onClick='adicionarFormularioAcao(this.id, " +
              rotaID +
              ", " +
              data.arrDados[i].ACO_ID +
              ");'/>";
            strHtml += "</td>";
            strHtml += "</tr>";
          }
          strHtml += "<tr class='bg-navy'>";
          strHtml +=
            "<th align='right' colspan='2'>Total de registro(s) " +
            data.arrDados.length +
            "</th>";
          strHtml += "</tr>";
          strHtml += "</tbody>";
          strHtml += "</table>";

          dialogAlert($("#hddLabelAcaoFormulario").val(), strHtml, 3);
        }
      }
    },
    "json"
  );
}

function salvarCamposTabela() {
  var strStatus = $.trim($("#hddAtivo").val());
  if ($("#RCT_Status").prop("checked"))
    strStatus = $.trim($("#hddInativo").val());

  if ($.trim($("#RCT_Campo").val()) == "") {
    dialogAlert(strAtencao, "Campo precisa ser informado.", 5, "");
  } else if ($.trim($("#RCT_Label").val()) == "") {
    dialogAlert(strAtencao, "Label precisa ser informado.", 5, "");
  } else if ($.trim($("#RCT_Regras").val()) == "") {
    dialogAlert(strAtencao, "Regra precisa ser informada.", 5, "");
  } else {
    $.post(
      $.trim($("#hddAcaoSalvarRegrasCampos").val()),
      {
        RTB_ID: $.trim($("#hddCodigoSelecionado").val()),
        ROT_ID2: $.trim($("#ROT_ID2").val()),
        RCT_ID: $.trim($("#RCT_ID").val()),
        RCT_Campo: $.trim($("#RCT_Campo").val()),
        RCT_Label: $.trim($("#RCT_Label").val()),
        RCT_Regras: $.trim($("#RCT_Regras").val()),
        RCT_Status: strStatus,
      },
      function (data) {
        //alert(data);
        if (data.sucesso == "true") {
          $("#div-success-modal").html(data.mensagem);
          $("#div-success-modal").show();
          $("#div-success-modal").fadeOut(parseInt($("#hddFadeOut").val()));

          //Limpando formulÃ¡rio do modal e recarregando o mestre detalhe
          $("#RCT_ID").val("");
          $("#RCT_Campo").val("");
          $("#RCT_Regras").val("");
          $("#RCT_Label").val("");
          $("#RCT_Status").prop("checked", false);
        } else {
          $("#div-danger-modal").html(data.mensagem);
          $("#div-danger-modal").show();
          $("#div-danger-modal").fadeOut(parseInt($("#hddFadeOut").val()));
        }

        carregarCamposTabelas();
      },
      "json"
    );
  }
}

function carregarCamposTabelas() {
  $("#divResultadoModal").html(strCarregando);

  $.post(
    $.trim($("#hddConsultarDadosCamposTabela").val()),
    { RTB_ID: $.trim($("#hddCodigoSelecionado").val()) },
    function (data) {
      //alert(data);
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          strHtml =
            "<table class='table table-bordered table-hover table-striped'>";
          strHtml += "<thead>";
          strHtml += "<tr>";
          strHtml +=
            "<td colspan='5' align='left'><b>Total Registro(s): " +
            data.arrDados.length +
            "</b></td>";
          strHtml += "</tr>";
          strHtml += "<tr>";
          strHtml += "<td align='left'><b>Tabela</b></td>";
          strHtml += "<td align='left'><b>Campo</b></td>";
          strHtml += "<td align='left'><b>Label</b></td>";
          strHtml += "<td align='center'></td>";
          strHtml += "<td align='center'></td>";
          strHtml += "</tr>";
          strHtml += "</thead>";
          strHtml += "<tbody>";

          for (var i = 0; i < data.arrDados.length; i++) {
            var strActionEditar =
              $.trim($("#hddAcaoCamposTabelaEditar").val()) +
              "/" +
              data.arrDados[i].RCT_ID;
            var strActionExcluir =
              $.trim($("#hddAcaoCamposTabelaExcluir").val()) +
              "/" +
              data.arrDados[i].RCT_ID;

            strHtml +=
              "<tr data-toggle='tooltip' title='Regras: " +
              data.arrDados[i].RCT_Regras +
              "'>";
            strHtml +=
              "<td align='left'>" + data.arrDados[i].RTB_Tabela + "</td>";
            strHtml +=
              "<td align='left'>" + data.arrDados[i].RCT_Campo + "</td>";
            strHtml +=
              "<td align='left'>" + data.arrDados[i].RCT_Label + "</td>";
            strHtml += "<td align='center'>";

            if (data.editar == true) {
              strHtml +=
                "<span style='cursor:pointer;' class='glyphicon glyphicon-pencil' onClick=\"editarRegrasCampos('" +
                strActionEditar +
                "');\";></span>";
            }

            strHtml += "</td>";
            strHtml += "<td align='center'>";

            if (data.excluir == true) {
              strHtml +=
                "<span style='cursor:pointer;' class='glyphicon glyphicon-trash' onClick=\"$('#hddExcluir').val('" +
                strActionExcluir +
                "');$('#descricaoExcluir').html('" +
                $.trim($("#hddLabelConfirmarNome").val()) +
                "<b>" +
                data.arrDados[i].RCT_Campo +
                "</b>');\" data-toggle='modal' data-target='#confirm-delete' class='btn btn-danger btn-sm' title='" +
                $.trim($("#hddTituloExcluir").val()) +
                "'></span>";
            }

            strHtml += "</td>";
            strHtml += "</tr>";
          }

          strHtml += "</tbody>";
          strHtml += "</table>";

          $("#divResultadoModal").html(strHtml);

          $(document).ready(function () {
            $('[data-toggle="tooltip"]').tooltip({ html: true });
          });
        } else {
          $("#divResultadoModal").html($("#hddSemDados").val());
        }
      } else {
        $("#divResultadoModal").html(data.mensagem);
      }
    },
    "json"
  );
}

function editarRegrasCampos(strAction) {
  $.post(
    $.trim(strAction),
    function (data) {
      //alert(data);
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          $("#RCT_ID").val(data.arrDados[0].RCT_ID);
          $("#RCT_Campo").val(data.arrDados[0].RCT_Campo);
          $("#RCT_Label").val(data.arrDados[0].RCT_Label);
          $("#RCT_Regras").val(data.arrDados[0].RCT_Regras);
          $("#RCT_Mensagens").val(data.arrDados[0].RCT_Mensagens);

          if (data.checar == $.trim($("#hddChecked").val())) {
            $("#RCT_Status").prop("checked", true);
          } else {
            $("#RCT_Status").prop("checked", false);
          }
        } else {
          dialogAlert(strAtencao, data.mensagem, 5, "");
          return;
        }
      } else {
        dialogAlert(strAtencao, data.mensagem, 5, "");
        return;
      }
    },
    "json"
  );
}

function exibirRegra(valor, tamanho) {
  return valor.substring(0, tamanho);
}

function carregarCalendario() {
  /* initialize the external events
     -----------------------------------------------------------------*/
  function ini_events(ele) {
    ele.each(function () {
      // create an Event Object (http://arshaw.com/fullcalendar/docs/event_data/Event_Object/)
      // it doesn't need to have a start or end
      var eventObject = {
        title: $.trim($(this).text()), // use the element's text as the event title
      };

      // store the Event Object in the DOM element so we can get to it later
      $(this).data("eventObject", eventObject);

      // make the event draggable using jQuery UI
      $(this).draggable({
        zIndex: 1070,
        revert: true, // will cause the event to go back to its
        revertDuration: 0, //  original position after the drag
      });
    });
  }

  ini_events($("#external-events div.external-event"));

  /* initialize the calendar
     -----------------------------------------------------------------*/
  //Date for the calendar events (dummy data)
  var date = new Date();
  var d = date.getDate(),
    m = date.getMonth(),
    y = date.getFullYear();
  $("#calendar").fullCalendar({
    ignoreTimezone: false,
    monthNames: [
      "Janeiro",
      "Fevereiro",
      "Março",
      "Abril",
      "Maio",
      "Junho",
      "Julho",
      "Agosto",
      "Setembro",
      "Outubro",
      "Novembro",
      "Dezembro",
    ],
    monthNamesShort: [
      "Jan",
      "Fev",
      "Mar",
      "Abr",
      "Mai",
      "Jun",
      "Jul",
      "Ago",
      "Set",
      "Out",
      "Nov",
      "Dez",
    ],
    dayNames: [
      "Domingo",
      "Segunda",
      "Terça",
      "Quarta",
      "Quinta",
      "Sexta",
      "Sábado",
    ],
    dayNamesShort: ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"],
    /*titleFormat: {
    month: 'MMMM yyyy',
    week: "d[ MMMM][ yyyy]{ - d MMMM yyyy}",
    day: 'dddd, d MMMM yyyy'
    },*/
    columnFormat: {
      month: "ddd",
      week: "ddd d",
      day: "",
    },
    axisFormat: "H:mm",
    timeFormat: {
      "": "H:mm",
      agenda: "H:mm{ - H:mm}",
    },
    buttonText: {
      prev: "Mês Anterior",
      next: "Próximo Mês",
      prevYear: "Ano Anterior",
      nextYear: "Prêximo Ano",
      today: "Hoje",
      month: "Mês",
      week: "Semana",
      day: "Dia",
    },
    header: {
      left: "prev,next today",
      center: "title",
      right: "month,agendaWeek,agendaDay",
    },
    //Random default events
    events: [
      /* {
          title: 'All Day Event',
          start: new Date(y, m, 1),
          backgroundColor: "#f56954", //red
          borderColor: "#f56954" //red
        },
        {
          title: 'Long Event',
          start: new Date(y, m, d - 5),
          end: new Date(y, m, d - 2),
          backgroundColor: "#f39c12", //yellow
          borderColor: "#f39c12" //yellow
        },
        {
          title: 'Meeting',
          start: new Date(y, m, d, 10, 30),
          allDay: false,
          backgroundColor: "#0073b7", //Blue
          borderColor: "#0073b7" //Blue
        },
        {
          title: 'Lunch',
          start: new Date(y, m, d, 12, 0),
          end: new Date(y, m, d, 14, 0),
          allDay: false,
          backgroundColor: "#00c0ef", //Info (aqua)
          borderColor: "#00c0ef" //Info (aqua)
        },
        {
          title: 'Birthday Party',
          start: new Date(y, m, d + 1, 19, 0),
          end: new Date(y, m, d + 1, 22, 30),
          allDay: false,
          backgroundColor: "#00a65a", //Success (green)
          borderColor: "#00a65a" //Success (green)
        },
        {
          title: 'Click for Google',
          start: new Date(y, m, 28),
          end: new Date(y, m, 29),
          url: 'http://google.com/',
          backgroundColor: "#3c8dbc", //Primary (light-blue)
          borderColor: "#3c8dbc" //Primary (light-blue)
        }*/
    ],
    editable: false,
    droppable: true, // this allows things to be dropped onto the calendar !!!
    drop: function (date, allDay) {
      // this function is called when something is dropped

      // retrieve the dropped element's stored Event Object
      var originalEventObject = $(this).data("eventObject");

      // we need to copy it, so that multiple events don't have a reference to the same object
      var copiedEventObject = $.extend({}, originalEventObject);

      // assign it the date that was reported
      copiedEventObject.start = date;
      copiedEventObject.allDay = allDay;
      copiedEventObject.backgroundColor = $(this).css("background-color");
      copiedEventObject.borderColor = $(this).css("border-color");

      // render the event on the calendar
      // the last `true` argument determines if the event "sticks" (http://arshaw.com/fullcalendar/docs/event_rendering/renderEvent/)
      $("#calendar").fullCalendar("renderEvent", copiedEventObject, true);

      // is the "remove after drop" checkbox checked?
      if ($("#drop-remove").is(":checked")) {
        // if so, remove the element from the "Draggable Events" list
        $(this).remove();
      }
    },
  });

  /* ADDING EVENTS */
  var currColor = "#3c8dbc"; //Red by default
  //Color chooser button
  var colorChooser = $("#color-chooser-btn");
  $("#color-chooser > li > a").click(function (e) {
    e.preventDefault();
    //Save color
    currColor = $(this).css("color");
    //Add color effect to button
    $("#add-new-event").css({
      "background-color": currColor,
      "border-color": currColor,
    });
  });
  $("#add-new-event").click(function (e) {
    e.preventDefault();
    //Get value and make sure it is not null
    var val = $("#new-event").val();
    if (val.length == 0) {
      return;
    }

    //Create events
    var event = $("<div />");
    event
      .css({
        "background-color": currColor,
        "border-color": currColor,
        color: "#fff",
      })
      .addClass("external-event");
    event.html(val);
    $("#external-events").prepend(event);

    //Add draggable funtionality
    ini_events(event);

    //Remove event from text input
    $("#new-event").val("");
  });
}

function salvarTerrenos() {
  if ($.trim($("#TER_Descricao").val()) == "") {
    $.notify("Descrição do terreno precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CAX_Gestor_ID").val()) == "") {
    $.notify("Gestor do terreno precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#TER_PontosTerreno").val()) == "" && $.trim($("#loteid").val()) == "") {
    $.notify("Coordenadas precisam ser informadas.", "warn");
    return;
  } else if ($.trim($("#CAX_Status_ID").val()) == "") {
      $.notify("Status do terreno precisa ser informado.", "warn");
      return;
  } else {
    preLoadingOpen();
    $("button").prop("disabled", true);

    $.ajax({
      url: $.trim($("#hddAcaoSalvarTerrenos").val()),
      dataType: "json",
      cache: false,
      data: {
        TER_ID: $.trim($("#TER_ID").val()),
        'COR_ID': $.trim($("#COR_ID").val()),
        ROT_ID2: $.trim($("#ROT_ID2").val()),
        TER_Descricao: $.trim($("#TER_Descricao").val()),
        CAX_Gestor_ID: $.trim($("#CAX_Gestor_ID").val()),
        TER_PontosTerreno: $.trim($("#TER_PontosTerreno").val()),
        TER_AreaTotal: area,
        CAX_Status_ID: $.trim($("#CAX_Status_ID").val()),
        TER_Endereco: $.trim($("#address-input").val()),
        TER_Cidade: $.trim($("#CID_ID option:selected").text()),
        TER_Setor: $.trim($("#TER_Setor").val()),
        TER_Quadra: $.trim($("#TER_Quadra").val()),
        loteid: $.trim($("#loteid").val()),
        setores: $.trim($("#setores").val()),
        quadras: $.trim($("#quadras").val()),
        lotes: $.trim($("#lotes").val()),
        areas: $.trim($("#areas").val()),
        TER_UsuResp: $.trim($("#TER_UsuResp").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        //alert(data); return;
        $("button").prop("disabled", false);
        preLoadingClose();
        redir(data.redir);
        return;
      })
      .fail(function (data) {
        //alert(data); return;
        $("button").prop("disabled", false);
        preLoadingOpen();
        dialogAlert(strAtencao, data.responseText, 6);
        return;
      });
  }
}

function pesquisarCEP(valor) {
  if (valor.length == 8) {
    $("#TER_Endereco").val("");
    //$('#UF_ID').val('');
    $("#TER_Cidade").val("");
    $("#TER_Bairro").val("");
    $("#TER_Numero").val("");
    $("#TER_Complemento").val("");

    $.post(
      $.trim($("#hddPesquisarCEP").val()),
      { CEP: valor },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#TER_Endereco").val(data.arrDados[0].END_EnderecoCompleto);
          $("#UF_ID").val(data.arrDadosCidades.UF_ID);

          if ($.trim(data.arrDados[0].CID_ID) != "") {
            carregarCidades(
              data.arrDadosCidades.UF_ID,
              data.arrDados[0].CID_ID
            );
          }

          if ($.trim(data.arrDados[0].BAI_ID) != "") {
            carregarBairros(
              data.arrDadosCidades.CID_ID,
              data.arrDados[0].BAI_ID
            );
          }
        }
        $("#UF_ID").trigger("chosen:updated");
      },
      "json"
    );
  }
}

function carregarCidades(estadoID, cidadeSelecionada) {
  $.post(
    $.trim($("#hddPesquisarCidades").val()),
    {
      UF_ID: estadoID,
      CID_ID: cidadeSelecionada,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          $("#TER_Cidade").val(data.arrDados[0]["CID_Descricao"]);
        }
      }
    },
    "json"
  );
}

function carregarBairros(cidadeID, bairroSelecionado) {
  $.post(
    $.trim($("#hddPesquisarBairros").val()),
    {
      CID_ID: cidadeID,
      BAI_ID: bairroSelecionado,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          $("#TER_Bairro").val(data.arrDados[0]["BAI_Descricao"]);
        }
      }
    },
    "json"
  );
}

function pesquisarCEPPorCodigos(valor) {
  if (valor.length == 8) {
    $("#INC_Endereco").val("");
    $("#UF_ID").val("");
    $("#CID_ID").val("");
    $("#BAI_ID").val("");

    $.post(
      $.trim($("#hddPesquisarCEP").val()),
      { CEP: valor },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#INC_Endereco").val(data.arrDados[0].END_EnderecoCompleto);
          $("#UF_ID").val(data.arrDadosCidades.UF_ID);

          if ($.trim(data.arrDados[0].CID_ID) != "") {
            carregarCidadesPorCodigos(
              data.arrDadosCidades.UF_ID,
              data.arrDados[0].CID_ID
            );
          }

          if ($.trim(data.arrDados[0].BAI_ID) != "") {
            carregarBairrosPorCodigos(
              data.arrDadosCidades.CID_ID,
              data.arrDados[0].BAI_ID
            );
          }
        }

        $("#UF_ID, #CID_ID, #BAI_ID").trigger("chosen:updated");
      },
      "json"
    );
  }
}

function carregarCidadesPorCodigos(estadoID, cidadeSelecionada) {
  $.post(
    $.trim($("#hddPesquisarCidadesPorCodigos").val()),
    {
      UF_ID: estadoID,
      CID_ID: cidadeSelecionada,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          strHtml = "<option value=''>" + strSelecione + "</option>";
          var selected = "";
          for (var i = 0; i < data.arrDados.length; i++) {
            selected = "";
            if (data.arrDados[i].CID_ID == data.CID_ID) {
              selected = "selected";
            }

            strHtml +=
              "<option " +
              selected +
              " value='" +
              data.arrDados[i].CID_ID +
              "'>" +
              data.arrDados[i].CID_Descricao +
              "</option>";
          }
          $("#CID_ID").html(strHtml);
        }
      }

      $("#CID_ID").trigger("chosen:updated");
    },
    "json"
  );
}

function carregarBairrosPorCodigos(cidadeID, bairroSelecionado) {
  $.post(
    $.trim($("#hddPesquisarBairrosPorCodigos").val()),
    {
      CID_ID: cidadeID,
      BAI_ID: bairroSelecionado,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          strHtml = "<option value=''>" + strSelecione + "</option>";
          var selected = "";
          for (var i = 0; i < data.arrDados.length; i++) {
            selected = "";
            if (data.arrDados[i].BAI_ID == data.BAI_ID) {
              selected = "selected";
            }

            strHtml +=
              "<option " +
              selected +
              " value='" +
              data.arrDados[i].BAI_ID +
              "'>" +
              data.arrDados[i].BAI_Descricao +
              "</option>";
          }

          $("#BAI_ID").html(strHtml);
        }
      }

      $("#BAI_ID").trigger("chosen:updated");
    },
    "json"
  );
}

function verificaExisteCorretor(valor) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddVerificaCorretorExiste").val()),
    {
      COR_ID: $.trim($("#COR_ID").val()),
      COR_Email: $.trim(valor),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();

        dialogAlert(strAtencao, data.mensagem, 5, "");
      } else {
        $("#btnSalvar").prop("disabled", false);

        preLoadingClose();
      }
    },
    "json"
  );
}

// TERRENOS CORRETORES
function limparTerrenosCorretores() {
  $("#TCO_ID").val("");
  $("#COR_ID").val("");
  $("#TCO_DataApresentacao").val("");
  // $('#TCO_Area').val('');
  // $("#TCO_ValorTotal").val("");
  // $('#TCO_ValorM2').val('');
  // $("#TCO_ValorPermutaFinanceira").val("");
  // $("#TCO_ValorPermutaFisica").val("");
  $("#TCO_CondicoesPagamentos").val("");
  $("#TCO_Observacoes").val("");
  $("#COR_ID").trigger("chosen:updated");
}

function salvarTerrenosCorretores() {
  if ($.trim($("#COR_ID").val()) == "") {
    $.notify("Corretor precisa ser informado.", "warn");
    return;
  } else {
    $("#btnAdicionarCorretor").prop("disabled", true);
    var strLabel = $("#btnAdicionarCorretor").html();
    $("#btnAdicionarCorretor").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddSalvarTerrenosCorretores").val()),
      dataType: "json",
      cache: false,
      data: {
        TCO_ID: $.trim($("#TCO_ID").val()),
        TER_ID: $.trim($("#TER_ID").val()),
        COR_ID: $.trim($("#COR_ID").val()),
        TCO_DataApresentacao: $.trim($("#TCO_DataApresentacao").val()),
        TCO_Area: $.trim($("#TCO_Area").val()),
        TCO_ValorTotal: $.trim($("#TCO_ValorTotal").val()),
        TCO_ValorPermutaFinanceira: $.trim(
          $("#TCO_ValorPermutaFinanceira").val()
        ),
        TCO_ValorPermutaFisica: $.trim($("#TCO_ValorPermutaFisica").val()),
        TCO_CondicoesPagamentos: $.trim($("#TCO_CondicoesPagamentos").val()),
        TCO_ValorM2: $.trim($("#TCO_ValorM2").val()),
        TCO_Observacoes: $.trim($("#TCO_Observacoes").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnAdicionarCorretor").prop("disabled", false);
        $("#btnAdicionarCorretor").html(strLabel);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");
        consultarTerrenosCorretores();
      })
      .fail(function (data) {
        $("#btnAdicionarCorretor").prop("disabled", false);
        $("#btnAdicionarCorretor").html(strLabel);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarTerrenosCorretores() {
  limparTerrenosCorretores();

  $("#divTerrenosEstudos").html("");
  $("#divProprietarios").html("");
  $("#divTerrenosViabilidades").html("");
  $("#divDocumentos").html("");
  $("#divObservacoes").html("");
  $("#tab_log").html("");
  $("#boxCorretores").show();
  $("#divCorretores").html(strCarregando);

  $.post(
    $.trim($("#hddConsultarTerrenosCorretores").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      visualizar: $("#hddVisualizarTerreno").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.totalRegistros > 0) {
          $("#divCorretores").html(data.strHtml);
        } else {
          $("#divCorretores").html("");
          $("#boxCorretores").hide();
        }
      } else {
        redir(data.redir);
      }

      setTimeout(function () {
        $("#COR_ID").chosen("destroy");
        $("#COR_ID").prop("selectedindex", -1);
        $(".chosen-select").chosen({
          case_sensitive_search: false,
          allow_single_deselect: true,
          disable_search_threshold: 5,
          width: "100%",
        });
        $("#COR_ID").chosen();
        //$("#COR_ID").trigger("chosen:updated");
      }, 500);
    },
    "json"
  );
}

function editarTerrenosCorretores(corretorTerrenoID) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddEditarTerrenosCorretores").val()),
    {
      TCO_ID: $.trim(corretorTerrenoID),
      TER_ID: $.trim($("#TER_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#TCO_ID").val(data.arrDados[0].TCO_ID);
        $("#COR_ID").val(data.arrDados[0].COR_ID);
        $("#TCO_DataApresentacao").val(data.arrDados[0].TCO_DataApresentacao);
        $("#TCO_Area").val(data.arrDados[0].TCO_Area);
        $("#TCO_ValorTotal").val(data.arrDados[0].TCO_ValorTotal);
        $("#TCO_ValorM2").val(data.arrDados[0].TCO_ValorM2);
        $("#TCO_ValorPermutaFinanceira").val(
          data.arrDados[0].TCO_ValorPermutaFinanceira
        );
        $("#TCO_ValorPermutaFisica").val(
          data.arrDados[0].TCO_ValorPermutaFisica
        );
        $("#TCO_CondicoesPagamentos").val(
          data.arrDados[0].TCO_CondicoesPagamentos
        );
        $("#TCO_Observacoes").val(data.arrDados[0].TCO_Observacoes);

        /* 			$("#COR_ID").chosen('destroy');
      $("#COR_ID").prop("selectedindex", -1);
      $(".chosen-select").chosen({ case_sensitive_search: false, allow_single_deselect: true, disable_search_threshold: 5, width:"100%" });
      $("#COR_ID").chosen(); */
        $("#COR_ID").trigger("chosen:updated");

        $("#btnAdicionarCorretor").html(
          $.trim($("#hddLabelBtnAlterar").val()) +
          " <i class='glyphicon glyphicon-ok-circle'></i> "
        );

        preLoadingClose();
      } else {
        preLoadingClose();

        redir(data.redir);
      }
    },
    "json"
  );
}

//TERRENOS PROPRIETARIOS CORRETORES
function limparTerrenosProprietariosCorretores() {
  $("#TPR_ID").val("");
  $("#TPR_Nome").val("");
  $("#TPR_Email").val("");
  $("#TPR_Telefone").val("");
  $("#TPR_Celular").val("");
  $("#TPR_NumeroContribuinte").val("");
  $("#TPR_Matricula").val("");
  $("#TPR_Cartorio").val("");
}

function salvarTerrenosProprietariosCorretores() {
  if ($.trim($("#TPR_Nome").val()) == "") {
    $.notify("Nome do proprietário precisa ser informado.", "warn");
    return;
  } else {
    $("#btnAdicionarProprieradio").prop("disabled", true);

    $.post(
      $.trim($("#hddCorretoresProprietariosSalvar").val()),
      {
        CTP_ID: $.trim($("#TPR_ID").val()),
        COR_ID: $.trim($("#COR_ID").val()),
        CTP_Nome: $.trim($("#TPR_Nome").val()),
        CTP_Email: $.trim($("#TPR_Email").val()),
        CTP_Telefone: $.trim($("#TPR_Telefone").val()),
        CTP_Celular: $.trim($("#TPR_Celular").val()),
        CTP_NumeroContribuinte: $.trim($("#TPR_NumeroContribuinte").val()),
        CTP_Matricula: $.trim($("#TPR_Matricula").val()),
        CTP_Cartorio: $.trim($("#TPR_Cartorio").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          limparTerrenosProprietariosCorretores();

          $.notify(data.mensagem, "success");

          consultarTerrenosProprietariosCorretores();

          $("#btnAdicionarProprieradio").html(
            $.trim($("#hddLabelBtnAdicionar").val()) +
            " <i class='glyphicon glyphicon-ok-circle'></i> "
          );
          $("#btnAdicionarProprieradio").prop("disabled", false);
        } else {
          redir(data.redir);
        }
      },
      "json"
    );
  }
}

/* QUADRO DE ÁREAS */

function quadroAreas(id_terreno, CAX_Gestor_ID) {

  preLoadingOpen();
  $("#divTerrenosQuadroAreas").html(strCarregando);

  $.ajax({
    url: $.trim($('#hddQuadroAreas').val()),
    cache: false,
    dataType: 'html',
    method: 'POST',
    type: 'POST',
    data: {
      id_terreno: id_terreno,
      CAX_Gestor_ID : CAX_Gestor_ID
    },
    success: function (data) {

      $('#divTerrenosQuadroAreas').html(data);

      preLoadingClose();

    }
  });
}


function limparTerrenosQuadroDeAreas() {
  $("#TQA_Tipo").val("");
  $("#TQA_DataLancamento").val("");
  $("#TQA_Unidades").val("");
  $("#TQA_Permuta").val("");
  $("#TQA_AreaPrivativa").val("");
  $("#TQA_ValorM2").val("");
  $("#TQA_Valor").val("");
}

function salvarTerrenosQuadroDeAreas() {

  if ($.trim($("#TQA_Tipo").val()) == "") {
    $.notify("Tipo Unidade precisa ser informado.", "warn");
    return;

  } else if ($.trim($("#EST_Fase").val()) == "") {
    $.notify("Fase precisa ser informada.", "warn");
    return;

  } else if ($.trim($("#TQA_Unidades").val()) == "") {
    $.notify("Qtde. Unidades precisa ser informada.", "warn");
    return;

  } else if ($.trim($("#TQA_AreaPrivativa").val()) == "") {
    $.notify("Área Privativa precisa ser informada.", "warn");
    return;

  } else if ($.trim($("#TQA_ValorM2").val()) == "") {
    $.notify("Valor m² precisa ser informado.", "warn");
    return;

  } else {
    $("#btnAdicionarQuadroAreas").prop("disabled", true);

    $.post(
      $.trim($("#hddQuadroAreasSalvar").val()),
      {
        TER_ID: $.trim($("#TER_ID").val()),
        TQA_Tipo: $.trim($("#TQA_Tipo").val()),
        TQA_Fase: $.trim($("#EST_Fase").val()),
        TQA_DataLancamento: $.trim($("#TQA_DataLancamento").val()),
        TQA_Permuta: $.trim($("#TQA_Permuta").val()),
        TQA_Unidades: $.trim($("#TQA_Unidades").val()),
        TQA_AreaPrivativa: $.trim($('#TQA_AreaPrivativa').val()),
        TQA_ValorM2: $.trim($('#TQA_ValorM2').val()),
        TQA_Valor: $.trim($('#TQA_Valor').val())
      },

      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          limparTerrenosQuadroDeAreas();

          $.notify(data.mensagem, "success");

          // consultarTerrenosProprietariosCorretores();

          $("#btnAdicionarQuadroAreas").html(
            $.trim($("#hddLabelBtnAdicionar").val()) +
            " <i class='glyphicon glyphicon-ok-circle'></i> "
          );
          $("#btnAdicionarQuadroAreas").prop("disabled", false);
          $("#quadro_areas_id").trigger('click');
        } else {

          $.notify(data.mensagem, "error");
          // redir(data.redir);
        }
      },
      "json"
    );
  }
}

function calculaValorUnidade() {

  var area_privativa = $.trim($('#TQA_AreaPrivativa').maskMoney('unmasked')[0])
  var valor_m2 = $.trim($('#TQA_ValorM2').maskMoney('unmasked')[0])

  var valor_unidade = area_privativa * valor_m2;

  return valor_unidade;

}

function atualizaEstruturaQuadro(intCodigo, strCampo, strValor) {

  if (strValor == "") {
    $.notify("Campo não pode ter valor vazio", "warn");
    return;
  }

  $.ajax({
    url: $.trim($('#hddQuadroAreasAtualizar').val()),
    dataType: 'json',
    cache: false,
    data: {
      TQA_ID: $.trim(intCodigo),
      TER_ID: $.trim($("#TER_ID").val()),
      strCampo: $.trim(strCampo),
      strValor: $.trim(strValor)
    },
    type: 'POST',
  }).success(function (data) {

    if (data.error) {
      dialogAlert(strAtencao, data.error.msg, 6);
      return;
    } else {
      $.notify("Informação atualizada com sucesso!", "success");

      let TQA_Unidades = data.dadosValores[0].TQA_Unidades;
      let TQA_Permuta = data.dadosValores[0].TQA_Permuta;
      let TQA_Valor = parseFloat(data.dadosValores[0].TQA_Valor);
      let TQA_VGV = parseFloat((TQA_Unidades * TQA_Valor));
      let total_unidades_cj = data.total_unidades_cj;
      let total_permutas = data.total_permutas;
      let total_area_privativa = parseFloat(data.total_area_privativa);
      let total_area_privativa_permuta = parseFloat(data.total_area_privativa_permuta);
      let total_vgv_unidades = parseFloat(data.total_vgv_unidades);
      let total_vgv_permutas = parseFloat(data.total_vgv_permutas);

      let area_menos_permuta = (total_area_privativa - total_area_privativa_permuta);
      let vgv_total_sem_perm = (total_vgv_unidades - total_vgv_permutas);

      let media_m2_total = (total_vgv_unidades / total_area_privativa);
      let media_m2_permuta = (total_vgv_permutas / total_area_privativa_permuta);
      if (isNaN(media_m2_permuta)) {
        media_m2_permuta = 0;
      }
      let media_m2_sem_perm = (vgv_total_sem_perm / area_menos_permuta);

      for (let i = 0; i < data.total_estruturas.length; i++) {
        let porcentagem = parseFloat(((data.total_estruturas[i].TQA_Unidades - data.total_estruturas[i].TQA_Permuta) / total_unidades_cj) * 100);
        $(`#coluna_porcentagem_${data.total_estruturas[i].TQA_ID} > center`).html(porcentagem.toLocaleString("pt-BR", { maximumFractionDigits: 2 }) + "%");
      }

      $(`#coluna_valor_${data.dadosValores[0].TQA_ID} > center`).html(TQA_Valor.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }));
      $(`#coluna_vgv_${data.dadosValores[0].TQA_ID} > center`).html(TQA_VGV.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }));

      $('#total_unidades > center').html((total_unidades_cj));
      $('#soma_unidades > center').html((total_unidades_cj - total_permutas));
      $('#soma_permutas > center').html(total_permutas);
      $('#total_area_privativa > center').html(total_area_privativa.toLocaleString("pt-BR", { minimumFractionDigits: 2 }));
      $('#total_area_privativa_permuta > center').html(total_area_privativa_permuta.toLocaleString("pt-BR", { minimumFractionDigits: 2 }));

      $(`#vgv_unidades > center`).html(total_vgv_unidades.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }));
      $(`#vgv_permutas > center`).html(total_vgv_permutas.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }));

      $(`#soma_area_privativa > center`).html(area_menos_permuta.toLocaleString("pt-BR", { minimumFractionDigits: 2 }));
      $('#vgv_total_sem_perm > center').html(vgv_total_sem_perm.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }));

      $('#media_m2_total > center').html(media_m2_total.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }));
      $('#media_m2_permuta > center').html(media_m2_permuta.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }));
      $('#media_m2_sem_perm > center').html(media_m2_sem_perm.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }));


    }

  }).fail(function (data) {
    $('.btn-formulario').prop('disabled', false);
    dialogAlert(strAtencao, data.responseText, 6);
  });
}

function consultarTerrenosProprietariosCorretores() {
  preLoadingOpen();

  $("#divCorretores").html("");
  $("#divDocumentos").html("");
  $("#boxProprietarios").show();
  $("#divProprietarios").html(strCarregando);

  $.post(
    $.trim($("#hddCorretoresProprietariosConsultar").val()),
    {
      COR_ID: $.trim($("#COR_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();

        $("#divProprietarios").html(data.strHtml);
      } else {
        preLoadingClose();

        redir(data.redir);
      }
    },
    "json"
  );
}

function editarTerrenosProprietariosCorretores(corretorProprietarioID) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddCorretoresProprietariosEditar").val()),
    {
      CTP_ID: $.trim(corretorProprietarioID),
      COR_ID: $.trim($("#COR_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#TPR_ID").val(data.arrDados[0].CTP_ID);
        $("#TPR_Nome").val(data.arrDados[0].CTP_Nome);
        $("#TPR_Email").val(data.arrDados[0].CTP_Email);
        $("#TPR_Telefone").val(data.arrDados[0].CTP_Telefone);
        $("#TPR_Celular").val(data.arrDados[0].CTP_Celular);
        $("#TPR_NumeroContribuinte").val(
          data.arrDados[0].CTP_NumeroContribuinte
        );
        $("#TPR_Matricula").val(data.arrDados[0].CTP_Matricula);
        $("#TPR_Cartorio").val(data.arrDados[0].CTP_Cartorio);

        $("#btnAdicionarProprieradio").html(
          $.trim($("#hddLabelBtnAlterar").val()) +
          " <i class='glyphicon glyphicon-ok-circle'></i> "
        );

        preLoadingClose();
      } else {
        preLoadingClose();

        redir(data.redir);
      }
      return;
    },
    "json"
  );
}

//TERRENOS PROPRIETARIOS
function limparTerrenosProprietarios() {
  $("#TPR_ID2").val("");
  $("#TPR_Nome").val("");
  $("#TPR_CPF_CNPJ").val("");
  $("#TPR_Email").val("");
  $("#TPR_Telefone").val("");
  $("#TPR_Celular").val("");
  $("#TPR_NumeroContribuinte").val("");
  $("#TPR_Matricula").val("");
  $("#TPR_Cartorio").val("");
  $("#TPR_Cartorio").val("");
  $("#TPR_Setor").val("");
  $("#TPR_Quadra").val("");
  $("#TPR_Lote").val("");

  $("#TPR_AreaTerreno").val("");
  $("#TPR_ValorVenal").val("");
  $("#TPR_TestadaUm").val("");
  $("#TPR_TestadaDois").val("");
  $("#TPR_TestadaTres").val("");
  $("#TPR_TestadaQuatro").val("");
  $("#TPR_ValorOferta").val("");
  $("#TPR_ValorPermuta").val("");
  $("#TPR_Comissao").val("");
  $("#TPR_ValorTotal").val("");
  $("#TPR_ValorM2").val("");

  $("#TPR_CondicoesPagamentos").val("");
  $("#TPR_Observacoes").val("");
}

function salvarTerrenosProprietarios() {
  if ($.trim($("#TPR_Nome").val()) == "") {
    dialogAlert(
      strAtencao,
      "Nome do proprietário precisa ser informado.",
      5,
      ""
    );
    return;
  } else {
    $("#btnAdicionarProprieradio").prop("disabled", true);

    $.post(
      $.trim($("#hddSalvarTerrenosProprietarios").val()),
      {
        TPR_ID: $.trim($("#TPR_ID2").val()),
        TER_ID: $.trim($("#TER_ID").val()),
        TPR_Nome: $.trim($("#TPR_Nome").val()),
        TPR_CPF_CNPJ: $.trim($("#TPR_CPF_CNPJ").val()),
        TPR_Email: $.trim($("#TPR_Email").val()),
        TPR_Telefone: $.trim($("#TPR_Telefone").val()),
        TPR_Celular: $.trim($("#TPR_Celular").val()),
        TPR_NumeroContribuinte: $.trim($("#TPR_NumeroContribuinte").val()),
        TPR_Matricula: $.trim($("#TPR_Matricula").val()),
        TPR_Cartorio: $.trim($("#TPR_Cartorio").val()),
        TPR_Setor: $.trim($("#TPR_Setor").val()),
        TPR_Quadra: $.trim($("#TPR_Quadra").val()),
        TPR_Lote: $.trim($("#TPR_Lote").val()),
        TPR_AreaTerreno: $.trim($("#TPR_AreaTerreno").val()),
        TPR_ValorVenal: $.trim($("#TPR_ValorVenal").val()),
        TPR_TestadaUm: $.trim($("#TPR_TestadaUm").val()),
        TPR_TestadaDois: $.trim($("#TPR_TestadaDois").val()),
        TPR_TestadaTres: $.trim($("#TPR_TestadaTres").val()),
        TPR_TestadaQuatro: $.trim($("#TPR_TestadaQuatro").val()),
        TPR_ValorOferta: $.trim($("#TPR_ValorOferta").val()),
        TPR_ValorPermuta: $.trim($("#TPR_ValorPermuta").val()),
        TPR_Comissao: $.trim($("#TPR_Comissao").val()),
        TPR_CondicoesPagamentos: $.trim($("#TPR_CondicoesPagamentos").val()),
        TPR_Observacoes: $.trim($("#TPR_Observacoes").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          limparTerrenosProprietarios();
          $.notify(data.mensagem, "success");
          consultarTerrenosProprietarios();

          $("#btnAdicionarProprieradio").html(
            $.trim($("#hddLabelBtnAdicionar").val()) +
            " <i class='glyphicon glyphicon-ok-circle'></i> "
          );
          $("#btnAdicionarProprieradio").prop("disabled", false);
          preLoadingClose();
        } else {
          preLoadingClose();

          redir(data.redir);
        }
      },
      "json"
    );
  }
}

function consultarTerrenosProprietarios() {
  preLoadingOpen();

  $("#tab_log, #divDocumentos, #divTerrenosViabilidades, #divTerrenosEstudos, #divObservacoes, #divCorretores").html("");
  $("#boxProprietarios").show();
  $("#divProprietarios").html(strCarregando);

  $.post(
    $.trim($("#hddConsultarTerrenosProprietarios").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      visualizar: $("#hddVisualizarTerreno").val(),
    },
    function (data) {
      if (data.sucesso == "true") {
        preLoadingClose();

        if (data.totalRegistros > 0) {
          $("#divProprietarios").html(data.strHtml);
        } else {
          $("#boxProprietarios").hide();
          $("#divProprietarios").html("");
        }
      } else {
        preLoadingClose();
        redir(data.redir);
      }
    },
    "json"
  );
}

function editarTerrenosProprietarios(corretorProprietarioID) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddEditarTerrenosProprietarios").val()),
    {
      TPR_ID: $.trim(corretorProprietarioID),
      TER_ID: $.trim($("#TER_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#TPR_ID2").val(data.arrDados[0].TPR_ID);
        $("#TPR_Nome").val(data.arrDados[0].TPR_Nome);
        $("#TPR_CPF_CNPJ").val(data.arrDados[0].TPR_CPF_CNPJ);
        $("#TPR_Email").val(data.arrDados[0].TPR_Email);
        $("#TPR_Telefone").val(data.arrDados[0].TPR_Telefone);
        $("#TPR_Celular").val(data.arrDados[0].TPR_Celular);
        $("#TPR_NumeroContribuinte").val(
          data.arrDados[0].TPR_NumeroContribuinte
        );
        $("#TPR_Matricula").val(data.arrDados[0].TPR_Matricula);
        $("#TPR_Cartorio").val(data.arrDados[0].TPR_Cartorio);
        $("#TPR_Setor").val(data.arrDados[0].TPR_Setor);
        $("#TPR_Quadra").val(data.arrDados[0].TPR_Quadra);
        $("#TPR_Lote").val(data.arrDados[0].TPR_Lote);
        $("#TPR_AreaTerreno").val(data.arrDados[0].TPR_AreaTerreno);
        $("#TPR_ValorVenal").val(data.arrDados[0].TPR_ValorVenal);
        $("#TPR_TestadaUm").val(data.arrDados[0].TPR_TestadaUm);
        $("#TPR_TestadaDois").val(data.arrDados[0].TPR_TestadaDois);
        $("#TPR_TestadaTres").val(data.arrDados[0].TPR_TestadaTres);
        $("#TPR_TestadaQuatro").val(data.arrDados[0].TPR_TestadaQuatro);
        $("#TPR_ValorOferta").val(data.arrDados[0].TPR_ValorOferta);
        $("#TPR_ValorPermuta").val(data.arrDados[0].TPR_ValorPermuta);
        $("#TPR_Comissao").val(data.arrDados[0].TPR_Comissao);
        $("#TPR_ValorTotal").val(data.arrDados[0].TPR_ValorTotal);
        $("#TPR_ValorM2").val(data.arrDados[0].TPR_ValorM2);
        $("#TPR_CondicoesPagamentos").val(
          data.arrDados[0].TPR_CondicoesPagamentos
        );
        $("#TPR_Observacoes").val(data.arrDados[0].TPR_Observacoes);

        $("#btnAdicionarProprieradio").html(
          $.trim($("#hddLabelBtnAlterar").val()) +
          " <i class='glyphicon glyphicon-ok-circle'></i> "
        );

        preLoadingClose();
      } else {
        preLoadingClose();

        redir(data.redir);
      }
    },
    "json"
  );
}

//TERRENOS DOCUMENTOS
function limparTerrenosDocumentos() {
  $("#TDO_ID").val("");
  $("#CAX_ID").val("");
  $("#TDO_Descricao").val("");
  $("#TDO_Anexo").val("");
  $("#divImagemAntiga").html("");
}

function salvarTerrenosDocumentos() {
  if ($.trim($("#TDO_Descricao").val()) == "") {
    dialogAlert(
      strAtencao,
      "Descrição do documento precisa ser informado.",
      5,
      ""
    );
    return;
  } else if ($.trim($("#CAX_ID").val()) == "") {
    dialogAlert(strAtencao, "Tipo do documento precisa ser informado.", 5, "");
    return;
  } else if (
    $.trim($("#TDO_ID").val()) == "" &&
    $.trim($("#TDO_Anexo").val()) == ""
  ) {
    dialogAlert(strAtencao, "Anexo do documento precisa ser informado.", 5, "");
    return;
  } else {
    $("#formDocumentos").submit();
  }
}

function consultarTerrenosDocumentos() {
  preLoadingOpen();

  $("#divObservacoes").html("");
  $("#divCorretores").html("");
  $("#divTerrenosEstudos").html("");
  $("#divProprietarios").html("");
  $("#divTerrenosViabilidades").html("");
  $("#tab_log").html("");
  $("#boxDocumentos").show();
  $("#divDocumentos").html(strCarregando);

  $.post(
    $.trim($("#hddConsultarTerrenosDocumentos").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      visualizar: $("#hddVisualizarTerreno").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.totalRegistros > 0) {
          $("#divDocumentos").html(data.strHtml);
        } else {
          $("#boxDocumentos").hide();
          $("#divDocumentos").html("");
        }

        //Carregar proprietários
        if (data.arrProprietarios != null) {
          $("#TPR_ID").html("");

          var strHtml =
            "<option selected value='0'>" + strSelecione + "</option>";
          for (var i = 0; i < data.arrProprietarios.length; i++) {
            strHtml +=
              "<option value='" +
              data.arrProprietarios[i].TPR_ID +
              "'>" +
              data.arrProprietarios[i].TPR_Nome +
              "</option>";
          }
          $("#TPR_ID").append(strHtml);
        }
      } else {
        $("#boxDocumentos").hide();
        $("#divDocumentos").html("");
        $("#boxDocumentos").hide();
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function editarTerrenosDocumentos(corretorDocumentoID) {
  preLoadingOpen();

  $("#TDO_ID").val("");
  $("#CAX_ID").val("");
  $("#TPR_ID").val("");
  $("#TPR_ID option").prop("selected", false);
  $("#TDO_Descricao").val("");
  $("#divImagemAntiga").html("");

  $.post(
    $.trim($("#hddEditarTerrenosDocumentos").val()),
    {
      TDO_ID: $.trim(corretorDocumentoID),
      TER_ID: $.trim($("#TER_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#TDO_ID").val(data.arrDados[0].TDO_ID);
        $("#CAX_ID").val(data.arrDados[0].CAX_ID);
        $('#TPR_ID >option[value="' + data.arrDados[0].TPR_ID + '"]').prop(
          "selected",
          true
        );
        $("#TDO_Descricao").val(data.arrDados[0].TDO_Descricao);

        if ($.trim(data.arrDados[0].TDO_Anexo) != "") {
          strHtml =
            "<a href='" +
            strDiretorioDocumentos +
            $.trim(data.arrDados[0].TDO_Anexo) +
            "' target='_blank' class='btn btn-primary btn-sm' title='Visualizar' data-toggle='tooltip'>";
          strHtml += "<span class='glyphicon glyphicon-file'></span>";
          strHtml += "</a>";

          $("#divImagemAntiga").html(strHtml);
        }

        $("#btnAdicionarDocumentos").html(
          $.trim($("#hddLabelBtnAlterar").val()) +
          " <i class='glyphicon glyphicon-ok-circle'></i> "
        );

        preLoadingClose();
      } else {
        preLoadingClose();

        redir(data.redir);
      }
    },
    "json"
  );
}

//TERRENOS OBSERVACOES
function limparTerrenosObservacoes() {
  $("#TOB_ID").val("");
  $("#TOB_Descricao").val("");
}

function salvarTerrenosObservacoes() {
  if ($.trim($("#TOB_Descricao").val()) == "") {
    dialogAlert(
      strAtencao,
      "Observação do terreno precisa ser informada.",
      5,
      ""
    );
    return;
  } else {
    //$('#btnAdicionarObservacao').prop('disabled', true);

    $.post(
      $.trim($("#hddSalvarTerrenosObservacoes").val()),
      {
        TER_ID: $.trim($("#TER_ID").val()),
        TOB_ID: $.trim($("#TOB_ID").val()),
        TOB_Descricao: $.trim($("#TOB_Descricao").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          limparTerrenosObservacoes();
          preLoadingClose();

          $.notify(data.mensagem, "success");

          consultarTerrenosObservacoes();

          $("#btnAdicionarObservacao").html(
            $.trim($("#hddLabelBtnAdicionar").val()) +
            " <i class='glyphicon glyphicon-ok-circle'></i> "
          );
          $("#btnAdicionarObservacao").prop("disabled", false);
        } else {
          preLoadingClose();

          redir(data.redir);
        }
      },
      "json"
    );
  }
}

function consultarTerrenosObservacoes() {
  preLoadingOpen();

  $("#divCorretores, #divTerrenosEstudos, #divProprietarios, #divTerrenosViabilidades, #divDocumentos").html("");
  $("#boxObservacoes").show();
  $("#divObservacoes").html(strCarregando);

  $.post(
    $.trim($("#hddConsultarTerrenosObservacoes").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      visualizar: $("#hddVisualizarTerreno").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.totalRegistros > 0) {
          $("#divObservacoes").html(data.strHtml);
        } else {
          $("#boxObservacoes").hide();
          $("#divObservacoes").html("");
        }
      } else {
        $("#divObservacoes").html("");
        $("#boxObservacoes").hide();
      }
      preLoadingClose();
    },
    "json"
  );
}

function editarTerrenosObservacoes(corretorObservacaoID) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddEditarTerrenosObservacoes").val()),
    {
      TOB_ID: $.trim(corretorObservacaoID),
      TER_ID: $.trim($("#TER_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#TOB_ID").val(data.arrDados[0].TOB_ID);
        $("#TOB_Descricao").val(data.arrDados[0].TOB_Descricao);

        $("#btnAdicionarObservacoes").html(
          $.trim($("#hddLabelBtnAlterar").val()) +
          " <i class='glyphicon glyphicon-ok-circle'></i> "
        );

        preLoadingClose();
      } else {
        preLoadingClose();

        redir(data.redir);
      }
    },
    "json"
  );
}

//FUNÇÕES GOOGLEMAPS
var map;
var marker;
var infoWindow;
var name;

function searchAddress() {
  //preLoadingOpen();

  var addressInput = document.getElementById("address-input").value;
  var geocoder = new google.maps.Geocoder();

  geocoder.geocode({ address: addressInput }, function (results, status) {
    if (status == google.maps.GeocoderStatus.OK) {
      var myResult = results[0].geometry.location;

      createMarker(myResult); //adicionar chamada à função que adiciona o marcador

      map.setCenter(myResult);
      map.setZoom(19);

      preLoadingClose();
    } else {
      preLoadingClose();
      dialogAlert(strAtencao, strLabelEnderecoNaoLocalizado, 5, "");
    }
  });
}

function createMarker(latlng) {
  // Se o utilizador efetuar outra pesquisa é necessário limpar a variável marker
  if (marker != undefined && marker != "") {
    marker.setMap(null);
    marker = "";
  }

  marker = new google.maps.Marker({
    map: map,
    position: latlng,
    icon: new google.maps.MarkerImage(
      strCaminhoProjeto + "assets/images/maps/pin.png"
    ),
  });
}

function exibirRaios() {
  checkcircle = document.getElementById("checkcircle").value;
  if (checkcircle == 1) {
    for (i in circle) {
      circle[i].setVisible(false);
    }
    checkcircle = document.getElementById("checkcircle").value = "2";
  } else {
    for (i in circle) {
      circle[i].setVisible(true);
    }
    checkcircle = document.getElementById("checkcircle").value = "1";
  }
}
/////////////////////

function validarEstudos() {
  var intEtapa = $.trim($("#hddEtapa").val());

  if (intEtapa == 1) {
    if ($.trim($("#EST_Descricao").val()) == "") {
      dialogAlert(strAtencao, "Descrição precisa ser informada.", 5, "");
      return;
    } else if ($("select[name='CAX_ID']").val() == null) {
      dialogAlert(strAtencao, "Tipo do estudo precisa ser informada.", 5, "");
      return;
    } else if ($.trim($("#EST_Area").val()) == "") {
      dialogAlert(strAtencao, "Área precisa ser informada.", 5, "");
      return;
    } else if ($("select[name='EMP_Tipologia[]']").val() == null) {
      dialogAlert(strAtencao, "Tipologia precisa ser informada.", 5, "");
      return;
    } else if ($.trim($("#coordenadas").val()) == "") {
      dialogAlert(strAtencao, "Poligono no mapa precisa ser informada.", 5, "");
      return;
    } else {
      $("#frmFormulario").submit();
    }
  } else if (intEtapa == 2) {
    var arrCodigosEmpreendimentos = new Array();
    $("input[type=checkbox][name='EMP_CodigoEmpreendimento[]']:checked").each(
      function () {
        arrCodigosEmpreendimentos.push($(this).val());
      }
    );
    if ($.trim($("#areapoligono").val()) == "") {
      dialogAlert(
        strAtencao,
        "Área de Influência precisa ser desenhada",
        5,
        ""
      );
      return;
    } else {
      $("#hddEmpreendimentosSelecioados").val(arrCodigosEmpreendimentos);
      $("#frmFormulario").submit();
    }

    //if (arrCodigosEmpreendimentos.length == 0){
    //dialogAlert(strAtencao, "No mínimo deve ser selecionado 1 (UM) empreendimento para o estudo.", 5, '');
    //return;
    //}else{
    //$('#hddEmpreendimentosSelecioados').val(arrCodigosEmpreendimentos);
    //$('#frmFormulario').submit();
    //}
  } else if (intEtapa == 3) {
    return;
  }
}

function consultarConcorrentes(concorrenteID) {
  preLoadingOpen();

  $("#divProdutos").html(strCarregando);

  $.post(
    $.trim($("#hddProdutosConsultar").val()),
    { EST_ID: $.trim(concorrenteID) },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();

        $("#divProdutos").html(data.strHtml);
      } else {
        dialogAlert(strAtencao, data.strHtml, 5, "");
      }
    },
    "json"
  );
}

function salvarDocumentosEstudos() {
  if ($.trim($("#CAX_ID").val()) == "") {
    dialogAlert(strAtencao, "Tipo precisa ser informado.", 5, "");
  } else if ($.trim($("#ESD_Descricao").val()) == "") {
    dialogAlert(strAtencao, "Descrição precisa ser informado.", 5, "");
  } else {
    $("#formAnexos").submit();
  }
}

function consultarEstudosDocumentos() {
  preLoadingOpen();

  $.post(
    $.trim($("#hddConsultarDocumentosEstudo").val()),
    { EST_ID: $.trim($("#EST_ID").val()) },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();

        $("#divEstudosDocumentos").html(data.strHtml);
      } else {
        dialogAlert(strAtencao, data.strHtml, 5, "");
      }
    },
    "json"
  );
}

function finalizarEstudo(estudoID) {
  preLoadingOpen();

  var strLabelFinalizar = $("#btnFinalizar").html();
  $("#btnFinalizar").prop("disabled", true);

  $("#btnFinalizar").html(strCarregando);

  $.post(
    $.trim($("#hddEstudoFinalizar").val()),
    { EST_ID: $.trim(estudoID) },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();

        $("#btnFinalizar").html($("#hddLabelFinalizado").val());
        $("#btnFinalizar").attr("title", data.titulo);

        $.notify(data.mensagem, "success");

        setTimeout(function () {
          redir("../Visualizar/" + data.estudoID, "parent");
        }, 1500);
      } else {
        $("#btnFinalizar").html(strLabelFinalizar);

        dialogAlert(strAtencao, data.mensagem, 5, "");
      }
    },
    "json"
  );
}

function calcularCorretorMetroQuadrado() {
  var valorM2 = $.trim($("#TCO_ValorM2").val());
  var areaValor = $.trim($("#TCO_Area").val());

  if (parseFloat(valorM2) > 0 && parseFloat(areaValor) > 0) {
    $.post(
      $.trim($("#hddClcularTerrenosCorretores").val()),
      {
        valorM2: valorM2,
        areaValor: areaValor,
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#TCO_ValorTotal").val(data.resultado);
        }
      },
      "json"
    );
  }
}
function calcularProprietarioValor() {
  var areaValor = $.trim($("#TPR_AreaTerreno").val());
  var valorOferta = $.trim($("#TPR_ValorOferta").val());
  var valorPermuta = $.trim($("#TPR_ValorPermuta").val());
  var valorComissao = $.trim($("#TPR_Comissao").val());

  //if (parseFloat(areaValor) > 0 && parseFloat(valorOferta) > 0  && parseFloat(valorPermuta) > 0){
  $.post(
    $.trim($("#hddClcularTerrenosProprietarios").val()),
    {
      areaValor: areaValor,
      valorOferta: valorOferta,
      valorPermuta: valorPermuta,
      valorComissao: valorComissao,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#TPR_ValorTotal").val(data.valortotal);
        $("#TPR_ValorM2").val(data.valorm2);
      }
    },
    "json"
  );
  //}
}

function calcularCorretorMetroQuadradoFormCorretores() {
  var valorM2 = $.trim($("#COR_ValorOferta").val());
  var areaValor = $.trim($("#COR_TamanhoTerreno").val());

  if (parseFloat(valorM2) > 0 && parseFloat(areaValor) > 0) {
    $.post(
      $.trim($("#hddCorretorCalcularValorM2").val()),
      {
        valorM2: valorM2,
        areaValor: areaValor,
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#COR_ValorM2Terreno").val(data.resultado);
        }
      },
      "json"
    );
  }
}

function calcularValorMetroQuadrado() {
  var valorTerreno = $.trim($("#VIA_ValorOferta").val());
  var areaTerreno = $.trim($("#VIA_TamanhoTerreno").val());

  if (parseFloat(valorTerreno) > 0 && parseFloat(areaTerreno) > 0) {
    $.post(
      $.trim($("#hddCalcularValorTerreno").val()),
      {
        valorTerreno: valorTerreno,
        areaTerreno: areaTerreno,
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#spnValorM2Terreno").html(data.resultado);
        }
      },
      "json"
    );
  }
}

/// VIABILIDADES TERRENOS
function limparViabilidadeTerreno() {
  $("#VTE_MesInicio").val("");
  $("#VTE_QuantidadeParcelas").val("");
  $("#VTE_ValorParcelas").val("");
  $("#VTE_Observacoes").val("");
}

function salvarViabilidadeTerreno() {
    if ($.trim($("#VTE_MesInicio").val()) == "") {
      $.notify("Mês de início precisa ser informado.", "warn");
      return;
    }else if ($.trim($("#VTE_QuantidadeParcelas").val()) == "") {
      $.notify("Quantidade de parcelas precisa ser informado ou ser maior que zero.", "warn");
      return;
    }else if ($.trim($("#VTE_ValorParcelas").val()) == "") {
      $.notify("Valor da parcela precisa ser informado ou ser maior que zero.", "warn");
      return;
    }else if ($.trim($("#VTE_MesInicio").val()) < -240 || $.trim($("#VTE_MesInicio").val()) > 240) {
      $.notify("Mês Início não pode ser menor que -240 e nem maior que 240.", "warn");
      return;
    }else{
      $('#btnSalvarViabilidadesTerreno').prop('disabeled', true);
      var strLabel = $('#btnSalvarViabilidadesTerreno').html();
      $('#btnSalvarViabilidadesTerreno, #resultadoViabilidadeTerreno').html(strCarregando);
      preLoadingOpen();
  
      $.ajax({
          url: $.trim($('#hddSalvarViabilidadesTerrenos').val()),
          dataType: 'json',
          cache: false,
          data: {
            VIA_ID: $.trim($("#VIA_ID").val()),
            VTE_MesInicio: $.trim($("#VTE_MesInicio").val()),
            VTE_QuantidadeParcelas: $.trim($("#VTE_QuantidadeParcelas").val()),
            VTE_ValorParcelas: $.trim($("#VTE_ValorParcelas").val()),
            VTE_Observacoes: $.trim($("#VTE_Observacoes").val())
          },
          type: 'POST',
      }).success(function(data){
        $("#btnSalvarDesembolso").prop("disabled", false);
        $("#btnSalvarDesembolso").html(strLabel);
        preLoadingClose();
  
        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }
  
        limparViabilidadeTerreno();
        consultarViabilidadeTerreno();
        $.notify(data.mensagem, "success");
    
      }).fail(function(data){
        $("#btnSalvarDesembolso").prop("disabled", false);
        $("#btnSalvarDesembolso").html(strLabel);
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });	

    }
}

function consultarViabilidadeTerreno() {
  $("#resultadoViabilidadeTerreno").html(strCarregando);
  $.post(
    $.trim($("#hddConsultarViabilidadesTerrenos").val()),
    { VIA_ID: $.trim($("#VIA_ID").val()) },
    function (data) {
      //alert(data); return;
      $("#resultadoViabilidadeTerreno").html(data.strHtml);
    },
    "json"
  );
}

function excluirViabilidadeTerreno() {
  var arrSelecionados = new Array();

  $("input[type=checkbox][name='items[]']:checked").each(function () {
    arrSelecionados.push($(this).val());
  });

  if (arrSelecionados.length > 0) {
    $("#confirm-delete").modal();
    $("#hddExcluirParametros").val(arrSelecionados);
    $("#hddExcluir").val(
      $.trim($("#hddExcluirViabilidadesTerrenos").val()) +
      "/" +
      $.trim($("#VIA_ID").val())
    );
    $("#descricaoExcluir").html(
      $("#hddLabelConfirmarSelecionados").val() +
      " " +
      arrSelecionados.length +
      " registro(s)."
    );
  } else {
    dialogAlert(
      strAtencao,
      "Selecione no minímo 1 (UMA) opção para excluir.",
      6,
      ""
    );
  }
}

//VIABILIDADES VENDAS
function salvarViabilidadeVendas() {
  if ($.trim($("#EST_TipoUnidade").val()) == "") {
    $.notify("Tipo Unidade precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#EST_QuantidadePermutas").val()) == "" ||
    parseInt($("#EST_QuantidadePermutas").val()) >
    parseInt($("#EST_QuantidadeUnidade").val())
  ) {
    $.notify(
      "Quantidade de permutas deve ser menor ou igual a quantidade de unidades.",
      "warn"
    );
    return;
  } else if (
    $.trim($("#EST_QuantidadeUnidade").val()) == "" ||
    $.trim($("#EST_QuantidadeUnidade").val()) == "0,00"
  ) {
    $.notify(
      "Quantidade de unidade precisa ser informado ou ser maior que zero.",
      "warn"
    );
    return;
  } else if (
    $.trim($("#EST_AreaPrivada").val()) == "" ||
    $.trim($("#EST_AreaPrivada").val()) == "0,00"
  ) {
    $.notify(
      "Área privada precisa ser informada ou ser maior que zero.",
      "warn"
    );
    return;
  } else if ($.trim($("#EST_Fase").val()) == "") {
    $.notify("Fase precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#SGP_SIMNao").val()) == "") {
    $.notify("Tipo Cálculo precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#EST_Tipo").val()) == "" ||
    $.trim($("#EST_Tipo").val()) <= 0
  ) {
    $.notify("Tipo Venda precisa ser informada ou ser maior que zero.", "warn");
    return;
  } else if (
    $.trim($("#EST_PrecoM2").val()) == "" ||
    $.trim($("#EST_PrecoM2").val()) == "0,00"
  ) {
    $.notify(
      "Preço do metro quadrado precisa ser informado ou ser maior que zero.",
      "warn"
    );
    return;
  } else if ($.trim($("#ATV_ID").val()) == "") {
    $.notify("Tabela precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#EST_PeriodoInicioVenda").val()) == "") {
    $.notify("Mês início vendas precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#EST_PeriodoJuros").val()) == "") {
    $.notify("Mês início juros precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#EST_PeriodoEntrega").val()) == "") {
    $.notify("Mês entrega vendas precisa ser informado.", "warn");
    return;
  } else {
    preLoadingOpen();
    $("#btnAdicionarVendasViabilidades").prop("disabled", true);
    $("#resultadoViabilidadeVendas").html(strCarregando);

    $.post(
      $.trim($("#hddSalvarViabilidadesVendas").val()),
      {
        EST_ID: $.trim($("#EST_ID").val()),
        VIA_ID: $.trim($("#VIA_ID").val()),
        EST_TipoUnidade: $.trim($("#EST_TipoUnidade").val()),
        EST_QuantidadeUnidade: $.trim($("#EST_QuantidadeUnidade").val()),
        EST_QuantidadePermutas: $.trim($("#EST_QuantidadePermutas").val()),
        EST_AreaPrivada: $.trim($("#EST_AreaPrivada").val()),
        EST_Fase: $.trim($("#EST_Fase").val()),
        EST_TravarMesEntrega: $.trim($("#SGP_SIMNao").val()),
        EST_Tipo: $.trim($("#EST_Tipo").val()),
        EST_PrecoM2: $.trim($("#EST_PrecoM2").val()),
        ATV_ID: $.trim($("#ATV_ID").val()),
        EST_JurosTabela: $.trim($("#EST_JurosTabela").val()),
        EST_PeriodoInicioVenda: $.trim($("#EST_PeriodoInicioVenda").val()),
        EST_PeriodoJuros: $.trim($("#EST_PeriodoJuros").val()),
        EST_PeriodoEntrega: $.trim($("#EST_PeriodoEntrega").val()),
        EST_MinhaCasaMinhaVida: $.trim($("#EST_MinhaCasaMinhaVida").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#EST_ID").val("");

          limparViabilidadeVendas();
          consultarViabilidadeVendas();
          preLoadingClose();

          $.notify(data.mensagem, "success");
        } else {
          $.notify(data.mensagem, "error");
        }
        $("#btnAdicionarVendasViabilidades").prop("disabled", false);
        $("#btnAdicionarVendasViabilidades").html(
          "<i class='glyphicon glyphicon-plus'></i> Adicionar"
        );
      },"json"
    );
  }
}

function consultarViabilidadeVendas() {
  $("#resultadoViabilidadeVendas").html(strCarregando);

  //$('#EST_ID, #EST_TipoUnidade, #EST_QuantidadeUnidade, #EST_QuantidadePermutas, #EST_AreaPrivada, #EST_Fase, #SGP_SIMNao, #EST_Tipo, #EST_PrecoM2, #ATV_ID, #EST_JurosTabela, #EST_PeriodoInicioVenda, #EST_PeriodoJuros, #EST_PeriodoEntrega, #EST_MinhaCasaMinhaVida').val('');
  $(
    "#EST_ID, #EST_TipoUnidade, #EST_QuantidadeUnidade, #EST_QuantidadePermutas, #EST_AreaPrivada, #EST_Fase, #SGP_SIMNao, #EST_Tipo, #EST_PrecoM2, #ATV_ID, #EST_JurosTabela, #EST_MinhaCasaMinhaVida"
  ).val("");

  $.post(
    $.trim($("#hddConsultarViabilidadesVendas").val()),
    { VIA_ID: $.trim($("#VIA_ID").val()) },
    function (data) {
      //alert(data); return;
      $("#resultadoViabilidadeVendas").html(data.strHtml);

      $("a[name='visualizarVendas[]']")
        .button()
        .click(function () {
          $.post(
            $.trim($("#hddViabilidadesVendasVisualizar").val()),
            {
              VIA_ID: $.trim($("#VIA_ID").val()),
              EST_ID: $.trim($("#hddCodigoSelecionado").val()),
              strTitulo:
                $.trim($("#VIA_Descricao").val()) +
                " (Tipo Unidade: " +
                $.trim($("#hddNomeSelecionado").val()) +
                ")",
            },
            function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                dialogAlert(data.strTitulo, data.strHtml, 3);
              }
            },
            "json"
          );
        });

      setTimeout(function () {
        $("#ATV_ID").chosen();
      }, 1000);
    },
    "json"
  );
}

function limparViabilidadeVendas() {
  $(
    "#EST_TipoUnidade, #EST_QuantidadeUnidade, #SGP_SIMNao, #EST_MinhaCasaMinhaVida, #EST_PeriodoEntrega, #EST_PeriodoInicioVenda, #EST_PeriodoJuros, #EST_JurosTabela, #ATV_ID, #EST_PrecoM2, #EST_Tipo, #EST_AreaPrivada, #EST_QuantidadePermutas"
  ).val("");
  $("#EST_Fase").val("1");
  $("#ATV_ID").trigger("chosen:updated");
}

function excluirViabilidadeVendas() {
  var arrSelecionados = new Array();

  $("input[type=checkbox][name='items[]']:checked").each(function () {
    arrSelecionados.push($(this).val());
  });

  if (arrSelecionados.length > 0) {
    $("#confirm-delete").modal();
    $("#hddExcluirParametros").val(arrSelecionados);
    $("#hddExcluir").val(
      $.trim($("#hddExcluirViabilidadesVendas").val()) +
      "/" +
      $.trim($("#VIA_ID").val())
    );
    $("#descricaoExcluir").html(
      $("#hddLabelConfirmarSelecionados").val() +
      " " +
      arrSelecionados.length +
      " registro(s)."
    );
  } else {
    dialogAlert(
      strAtencao,
      "Selecione no minímo 1 (UMA) opção para excluir.",
      6,
      ""
    );
  }
}

function calcularCustoObra() {
  var valorArea = textoParaFloat($("#COB_AreaContruida").val());
  var valorCusto = textoParaFloat($("#COB_CustoM2").val());

  if (valorArea > 0 && valorCusto > 0) {
    $.post(
      $.trim($("#hddCalcularViabilidadesCurvas").val()),
      {
        valorArea: $.trim(valorArea),
        valorCusto: $.trim(valorCusto),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#COB_CustoObra").val(data.valor);
        } else {
          $("#COB_CustoObra").val("");
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  }
}

//VIABILIDADES CURVAS
function salvarViabilidadeCurvas() {
  if ($.trim($("#ACU_ID").val()) == "") {
    $.notify("Curva precisa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#COB_AreaContruida").val()) == "") {
    $.notify("Área construída precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#COB_CustoM2").val()) == "") {
    $.notify("Custro metro quadrado precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#COB_MesInicio").val()) == "") {
    $.notify("Mês início precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#COB_Fase").val()) == "") {
    $.notify("Fase precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#COB_TaxaAdministrativa").val()) == "") {
    $.notify("Taxa administrativa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#COB_CarenciaTaxaAdministrativa").val()) == "") {
    $.notify("Carência taxa administrativa precisa ser informada.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnAdicionarCurvasObras").html();
    $("#btnAdicionarCurvasObras, #resultadoViabilidadeCurvas").html(
      strCarregando
    );
    //$('#btnAdicionarPeriodico').html("<i class='glyphicon glyphicon-plus'></i> "+$('#hddLabelBtnAdicionar').val());
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#hddSalvarViabilidadesCurvas").val()),
      dataType: "json",
      cache: false,
      data: {
        COB_ID: $.trim($("#COB_ID").val()),
        VIA_ID: $.trim($("#VIA_ID").val()),
        ACU_ID: $.trim($("#ACU_ID").val()),
        COB_AreaContruida: $.trim($("#COB_AreaContruida").val()),
        COB_CustoM2: $.trim($("#COB_CustoM2").val()),
        COB_CustoObra: $.trim($("#COB_CustoObra").val()),
        COB_MesInicio: $.trim($("#COB_MesInicio").val()),
        COB_Fase: $.trim($("#COB_Fase").val()),
        COB_TaxaAdministrativa: $.trim($("#COB_TaxaAdministrativa").val()),
        COB_CarenciaTaxaAdministrativa: $.trim(
          $("#COB_CarenciaTaxaAdministrativa").val()
        ),
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario, #COB_Fase").prop("disabled", false);
        $("#btnAdicionarCurvasObras").html(strLabel);

        if (data.error) {
          preLoadingClose();
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        } else {
          $.notify(data.mensagem, "success");

          limparViabilidadeCurvas();
          consultarViabilidadeCurvas();
          preLoadingClose();
        }
      })
      .fail(function (data) {
        $(".btn-formulario, #COB_Fase").prop("disabled", false);
        $("#btnAdicionarCurvasObras").html(strLabel);
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function editarViabilidadesCurvas(viabilidadeID, curvaID) {
  preLoadingOpen();

  $("#btnAdicionarCurva").html(
    "<i class='glyphicon glyphicon-refresh'></i> " +
    $("#hddLabelBtnAtualizar").val()
  );

  $.post(
    $.trim($("#hddEditarrViabilidadesCurvas").val()) +
    "/" +
    $.trim(viabilidadeID) +
    "/" +
    $.trim(curvaID),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#COB_ID").val(data.arrDados[0]["COB_ID"]);
        $("#ACU_ID").val(data.arrDados[0]["ACU_ID"]);
        $("#COB_AreaContruida").val(data.arrDados[0]["COB_AreaContruida"]);
        $("#COB_CustoM2").val(data.arrDados[0]["COB_CustoM2"]);
        $("#COB_CustoObra").val(data.arrDados[0]["COB_CustoObra"]);
        $("#COB_MesInicio").val(data.arrDados[0]["COB_MesInicio"]);
        $("#COB_Fase").val(data.arrDados[0]["COB_Fase"]);
        $("#COB_TaxaAdministrativa").val(
          data.arrDados[0]["COB_TaxaAdministrativa"]
        );
        $("#COB_CarenciaTaxaAdministrativa").val(
          data.arrDados[0]["COB_CarenciaTaxaAdministrativa"]
        );

        $("#COB_Fase").prop("disabled", true);
      } else {
        dialogAlert(strAtencao, data.mensagem, 6, "");
      }
      preLoadingClose();
    },
    "json"
  );
}

function consultarViabilidadeCurvas() {
  $("#resultadoViabilidadeCurvas").html(strCarregando);
  $.post(
    $.trim($("#hddConsultarViabilidadesCurvas").val()),
    { VIA_ID: $.trim($("#VIA_ID").val()) },
    function (data) {
      //alert(data); return;
      $("#resultadoViabilidadeCurvas").html(data.strHtml);

      //Exibir dialog com os ROTAS para adicionar/remover ao MÓDULO;
      $("a[name='visualizarCurvas[]']")
        .button()
        .click(function () {
          $.post(
            $.trim($("#hddVisualizarViabilidadesCurvas").val()),
            {
              COB_ID: $.trim($("#hddCodigoSelecionado").val()),
              ACU_Descricao: $.trim($("#hddNomeSelecionado").val()),
            },
            function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                dialogAlert(data.strTitulo, data.strHtml, 3);
              }
            },
            "json"
          );
        });
    },
    "json"
  );
}

function limparViabilidadeCurvas() {
  $(
    "#ACU_ID, #COB_AreaContruida, #COB_CustoM2, #COB_CustoObra, #COB_MesInicio, #COB_Fase, #COB_TaxaAdministrativa, #COB_CarenciaTaxaAdministrativa"
  ).val("");
}

function excluirViabilidadeCurvas() {
  var arrSelecionados = new Array();

  $("input[type=checkbox][name='items[]']:checked").each(function () {
    arrSelecionados.push($(this).val());
  });

  if (arrSelecionados.length > 0) {
    $("#confirm-delete").modal();
    $("#hddExcluirParametros").val(arrSelecionados);
    $("#hddExcluir").val(
      $.trim($("#hddExcluirViabilidadesCurvas").val()) +
      "/" +
      $.trim($("#VIA_ID").val())
    );
    $("#descricaoExcluir").html(
      $("#hddLabelConfirmarSelecionados").val() +
      " " +
      arrSelecionados.length +
      " registro(s)."
    );
  } else {
    dialogAlert(
      strAtencao,
      "Selecione no minímo 1 (UMA) opção para excluir.",
      6,
      ""
    );
  }
}

function verificaBtnTabelaVendas() {
  $("#btnSalvar").prop("disabled", true);
  var strLabel = $("#btnSalvar").html();
  $("#btnSalvar").html(strCarregando);

  var strCorRecebimento = $("#total-serie").css("color");
  var strCorVelocidade = $("#total-percentual").css("color");

  if (strCorRecebimento == "rgb(50, 205, 50)" && strCorVelocidade == "rgb(50, 205, 50)") {
    $("#btnSalvar").prop("disabled", false);
    $("#btnSalvar").html(strLabel);
    return true;
  }

  $("#btnSalvar").html(strLabel);
  return false;
}

function verificaBtnCurvas() {
  $("#btnSalvar").prop("disabled", true);
  var strLabel = $("#btnSalvar").html();
  $("#btnSalvar").html(strCarregando);

  var strCorCurva = $("#total-percentual").css("color");
  if (strCorCurva == "rgb(50, 205, 50)") {
    $("#btnSalvar").prop("disabled", false);
    $("#btnSalvar").html(strLabel);
    return true;
  }

  $("#btnSalvar").html(strLabel);
  return false;
}

function salvarViabilidadesTabelasVendas() {
  if ($.trim($("#ATV_Descricao").val()) == "") {
    dialogAlert(strAtencao, "Descrição precisa ser informada.", 6, "");
    return;
  } else {
    if (verificaBtnTabelaVendas() == true) {
      $(".btn-formulario").prop("disabled", true);
      var strLabel = $("#btnSalvar").html();
      $("#btnSalvar").html(strCarregando);
      preLoadingOpen();

      //Verifica as informações da Condição de Recebimentos
      var arrTipos = new Array();
      var arrParcelas = new Array();
      var arrPercentuais = new Array();
      var arrMesInicio = new Array();

      $("select[name='ACT_Descricao[]']").each(function () {
        if ($.trim($(this).val()) != "") {
          arrTipos.push($(this).val());
        }
      });

      $("input[type=text][name='ACT_Parcelas[]']").each(function () {
        if ($.trim($(this).val()) != "") {
          arrParcelas.push($(this).val());
        }
      });

      $("input[type=text][name='ACT_PercentualTotalSerie[]']").each(
        function () {
          if ($.trim($(this).val()) != "" && $.trim($(this).val()) != "0,00") {
            arrPercentuais.push($(this).val());
          }
        }
      );

      $("input[type=text][name='ACT_MesInicio[]']").each(function () {
        // if ($.trim($(this).val()) != ''){
        arrMesInicio.push($(this).val());
        // }
      });

      //Verifica as informações de Velocidade Vendas
      var arrPeriodo = new Array();
      var arrPercentuaisVelocidade = new Array();

      $("input[type=text][name='AVT_QuantidadeUnidades[]']").each(function () {
        if ($.trim($(this).val()) != "" && $.trim($(this).val()) != "0,00") {
          arrPercentuaisVelocidade.push($(this).val());
        }
      });

      $("input[type=text][name='AVT_MesInicio[]']").each(function () {
        // if ($.trim($(this).val()) != ''){
        arrPeriodo.push($(this).val());
        // }
      });

      //Valores OK
      if (
        arrTipos.length == arrMesInicio.length &&
        arrParcelas.length == arrPercentuais.length &&
        arrMesInicio.length == arrParcelas.length &&
        arrPeriodo.length == arrPercentuaisVelocidade.length
      ) {
        $.ajax({
          url: $.trim($("#hddSalvarViabilidadesTabelasVendas").val()),
          dataType: "json",
          cache: false,
          data: {
            ATV_Descricao: $.trim($("#ATV_Descricao").val()),
            arrTipos: arrTipos,
            arrParcelas: arrParcelas,
            arrPercentuais: arrPercentuais,
            arrMesInicio: arrMesInicio,
            arrPeriodo: arrPeriodo,
            arrPercentuaisVelocidade: arrPercentuaisVelocidade,
          },
          type: "POST",
        })
          .success(function (data) {
            $(".btn-formulario").prop("disabled", false);
            $("#btnSalvar").html(strLabel);
            preLoadingClose();

            if (data.error) {
              dialogAlert(strInformacao, data.error.msg, 6);
              return;
            }

            $.notify(data.mensagem, "success");

            setTimeout(function () {
              redir(data.redir, "parent");
            }, 2000);
          })
          .fail(function (data) {
            $(".btn-formulario").prop("disabled", false);
            $("#btnSalvar").html(strLabel);
            preLoadingClose();

            dialogAlert(strAtencao, data.responseText, 6);
          });
      } else {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvar").html(strLabel);
        preLoadingClose();

        dialogAlert(
          strAtencao,
          "Verifique se todos os campos de parcelas e períodos estão preenchidos corretamente.",
          6,
          ""
        );
      }
    } else {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvar").html(strLabel);
      preLoadingClose();

      dialogAlert(
        strAtencao,
        "Verifique se todos os campos de parcelas e períodos estão preenchidos corretamente.",
        6,
        ""
      );
    }
  }
}

function salvarViabilidadesCurvasObras() {
  if ($.trim($("#ACU_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ACU_Prazo").val()) == "") {
    $.notify("Prazo precisa ser informada.", "warn");
    return;
  } else {
    if (verificaBtnCurvas() == true) {
      //Verifica as informações do Apoio Curvas Fluxos
      var arrPeriodo = new Array();
      var arrPercentuais = new Array();

      $("input[type=text][name='ACF_PercentualCurva[]']").each(function () {
        if ($.trim($(this).val()) != "") {
          arrPercentuais.push($(this).val());
        }
      });

      $("input[type=text][name='ACF_Periodo[]']").each(function () {
        if ($.trim($(this).val()) != "") {
          arrPeriodo.push($(this).val());
        }
      });

      //Valores OK
      if (arrPeriodo.length == arrPercentuais.length) {
        var strLabel = consultarPadraoInicial(false);

        $.ajax({
          url: $.trim($('#hddSalvarViabilidadesCurvasObras').val()),
          dataType: 'json',
          cache: false,
          data: {
            ACU_Descricao: $.trim($("#ACU_Descricao").val()),
            ACU_Prazo: $.trim($("#ACU_Prazo").val()),
            arrPeriodo: arrPeriodo,
            arrPercentuais: arrPercentuais,
          },
          type: 'POST',
        }).success(function (data) {
          consultarPadraoSucesso(strLabel, false);

          if (data.error) {
            consultarPadraoExcessao();
            dialogAlert(strAtencao, data.error.msg, 6);
            return;
          }

          $.notify(data.mensagem, "success");

          setTimeout(function () {
            redir(data.redir);
          }, 1500);

        }).fail(function (data) {
          consultarPadraoFalha(strLabel, false);
          dialogAlert(strAtencao, data.responseText, 6);
        });

      } else {
        $.notify("Verifique se todos os campos de parcelas e períodos estão preenchidos corretamente.", "error");
      }
    } else {
      $.notify("Verifique se todos os campos de parcelas e períodos estão preenchidos corretamente.", "error");
    }
  }
}

function salvarViabilidadesPeriodicos() {
  if ($.trim($("#CAX_ID").val()) == "") {
    dialogAlert(strAtencao, "Grupo precisa precisa ser informada.", 5, "");
    return;
  } else if ($.trim($("#PER_Descricao").val()) == "") {
    dialogAlert(strAtencao, "Descrição precisa ser informada.", 5, "");
    return;
  } else if ($.trim($("#PER_QuantidadeParcelas").val()) == "") {
    dialogAlert(strAtencao, "Quantidade precisa ser informada.", 5, "");
    return;
  } else if ($.trim($("#PER_PeriodoInicio").val()) == "") {
    dialogAlert(strAtencao, "Mês início precisa ser informado.", 5, "");
    return;
  } else if ($.trim($("#PER_ValorParcela").val()) == "") {
    dialogAlert(strAtencao, "Valor parcela precisa ser informado.", 5, "");
    return;
  } else if ($.trim($("#PER_Natureza").val()) == "") {
    dialogAlert(
      strAtencao,
      "Operação da natureza precisa ser informada..",
      5,
      ""
    );
    return;
  } else {
    preLoadingOpen();

    $("#resultadoViabilidadesPeriodicos").html(strCarregando);
    $("#btnAdicionarPeriodico").html(
      "<i class='glyphicon glyphicon-plus'></i> " +
      $("#hddLabelBtnAdicionar").val()
    );

    $.post(
      $.trim($("#hddSalvarViabilidadesPeriodicos").val()),
      {
        PER_ID: $.trim($("#PER_ID").val()),
        VIA_ID: $.trim($("#VIA_ID").val()),
        CAX_ID: $.trim($("#CAX_ID").val()),
        PER_Descricao: $.trim($("#PER_Descricao").val()),
        PER_Observacao: $.trim($("#PER_Observacao").val()),        
        PER_QuantidadeParcelas: $.trim($("#PER_QuantidadeParcelas").val()),
        PER_Multiplicador: $.trim($("#PER_Multiplicador").val()),
        PER_PeriodoInicio: $.trim($("#PER_PeriodoInicio").val()),
        PER_ValorParcela: $.trim($("#PER_ValorParcela").val()),
        PER_ValorTotal: $.trim($("#PER_ValorTotal").val()),
        PER_Natureza: $.trim($("#PER_Natureza").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          limparViabilidadesPeriodicos();
          consultarViabilidadesPeriodicos();
          preLoadingClose();

          $.notify(data.mensagem, "success");
        } else {
          dialogAlert(strAtencao, data.mensagem, 5, "");
        }
      },
      "json"
    );
  }
}

function calcularValorTotalApoios() {
  var douValorParcela = $("#APR_ValorParcela").val();
  var intQuantidadeParcelas = $("#APR_QuantidadeParcelas").val();

  if (parseFloat(douValorParcela) > 0 && parseInt(intQuantidadeParcelas) > 0) {
    //
    $.post(
      $.trim($("#hddCalcularPeriodicos").val()),
      {
        douValorParcela: douValorParcela,
        intQuantidadeParcelas: intQuantidadeParcelas,
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          //$('#spnValorM2Terreno').html(data.resultado);
          $("#APR_ValorTotal").val(data.resultado);
        }
      },
      "json"
    );
  }
}

function calcularValorTotalPeriodicos() {
  var douValorParcela = $("#PER_ValorParcela").val();
  var intQuantidadeParcelas = $("#PER_QuantidadeParcelas").val();

  if (parseFloat(douValorParcela) > 0 && parseInt(intQuantidadeParcelas) > 0) {
    //
    $.post(
      $.trim($("#hddCalcularPeriodicos").val()),
      {
        douValorParcela: douValorParcela,
        intQuantidadeParcelas: intQuantidadeParcelas,
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          //$('#spnValorM2Terreno').html(data.resultado);
          $("#PER_ValorTotal").val(data.resultado);
        }
      },
      "json"
    );
  }
}

function editarPeriodico(viabilidadeID, periodicoID) {
  preLoadingOpen();

  $("#btnAdicionarPeriodico").html(
    "<i class='glyphicon glyphicon-refresh'></i> " +
    $("#hddLabelBtnAtualizar").val()
  );

  $.post(
    $.trim($("#hddEditarViabilidadesPeriodicos").val()) +
    "/" +
    $.trim(viabilidadeID) +
    "/" +
    $.trim(periodicoID),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#PER_ID").val(data.arrDados[0]["PER_ID"]);
        $("#CAX_ID").val(data.arrDados[0]["CAX_ID"]);
        $("#PER_Descricao").val(data.arrDados[0]["PER_Descricao"]);
        $("#PER_Observacao").val(data.arrDados[0]["PER_Observacao"]);
        $("#PER_QuantidadeParcelas").val(
          data.arrDados[0]["PER_QuantidadeParcelas"]
        );
        $("#PER_Multiplicador").val(
          data.arrDados[0]["PER_Multiplicador"] +
          "|" +
          data.arrDados[0]["PER_Tipo"]
        );
        $("#PER_PeriodoInicio").val(data.arrDados[0]["PER_PeriodoInicio"]);
        $("#PER_ValorParcela").val(data.arrDados[0]["PER_ValorParcela"]);
        $("#PER_ValorTotal").val(data.arrDados[0]["PER_ValorTotal"]);
        $("#PER_Natureza").val(data.arrDados[0]["PER_Natureza"]);
      } else {
        dialogAlert(strAtencao, data.mensagem, 6, "");
      }

      preLoadingClose();
    },
    "json"
  );
}

function excluirViabilidadesPeriodicos() {
  var arrSelecionados = new Array();

  $("input[type=checkbox][name='items[]']:checked").each(function () {
    arrSelecionados.push($(this).val());
  });

  if (arrSelecionados.length > 0) {
    $("#confirm-delete").modal();
    $("#hddExcluirParametros").val(arrSelecionados);
    $("#hddExcluir").val(
      $.trim($("#hddExcluirViabilidadesPeriodicos").val()) +
      "/" +
      $.trim($("#VIA_ID").val())
    );
    $("#descricaoExcluir").html(
      $("#hddLabelConfirmarSelecionados").val() +
      " " +
      arrSelecionados.length +
      " registro(s)."
    );
  } else {
    dialogAlert(
      strAtencao,
      "Selecione no minímo 1 (UMA) opção para excluir.",
      6,
      ""
    );
  }
}

function consultarViabilidadesPeriodicos() {
  $("#resultadoViabilidadesPeriodicos").html(strCarregando);
  $.post(
    $.trim($("#hddConsultarViabilidadesPeriodicos").val()),
    { VIA_ID: $.trim($("#VIA_ID").val()) },
    function (data) {
      //alert(data); return;
      $("#resultadoViabilidadesPeriodicos").html(data.strHtml);

      //Exibir dialog com os ROTAS para adicionar/remover ao MÓDULO;
      $("a[name='visualizar[]']")
        .button()
        .click(function () {
          $.post(
            $.trim($("#hddVisualizarViabilidadesPeriodicos").val()),
            {
              PER_ID: $.trim($("#hddCodigoSelecionado").val()),
              PER_Descricao: $.trim($("#hddNomeSelecionado").val()),
            },
            function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                dialogAlert(data.strTitulo, data.strHtml, 3);
              }
            },
            "json"
          );
        });
    },
    "json"
  );
}

function limparViabilidadesPeriodicos() {
  $("#CAX_ID").val("");
  $("#PER_Descricao").val("");
  $("#PER_QuantidadeParcelas").val("");
  $("#PER_PeriodoInicio").val("");
  $("#PER_Multiplicador").val("");
  $("#PER_ValorParcela").val("");
  $("#PER_ValorTotal").val("");
  $("#PER_Natureza").val("");
}

function salvarViabilidadesProporcionais() {
  if ($.trim($("#CAX_ID2").val()) == "") {
    $.notify("Grupo precisa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#PPC_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#PPC_QuantidadeParcelas").val()) == "") {
    $.notify("Quantidade precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#PPC_PeriodoInicio").val()) == "") {
    $.notify("Mês início precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#PPC_Natureza").val()) == "") {
    $.notify("Operação da natureza precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#PPC_Multiplicador").val()) == "") {
    $.notify("Periodicidade precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#PPC_PercentualConta").val()) == "") {
    $.notify("Percentual da conta precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#PPC_ContaDestino").val()) == "") {
    $.notify("Conta destino precisa ser informada.", "warn");
    return;
  } else {
    preLoadingOpen();

    $("#resultadoViabilidadesProporcionais").html(strCarregando);
    $("#btnSalvarProporcionais").html(
      "<i class='glyphicon glyphicon-plus'></i> " +
      $("#hddLabelBtnAdicionar").val()
    );

    $.post(
      $.trim($("#hddSalvarViabilidadesProporcionais").val()),
      {
        PPC_ID: $.trim($("#PPC_ID").val()),
        VIA_ID: $.trim($("#VIA_ID").val()),
        CAX_ID: $.trim($("#CAX_ID2").val()),
        PPC_Descricao: $.trim($("#PPC_Descricao").val()),
        PPC_Observacao: $.trim($("#PPC_Observacao").val()),
        PPC_QuantidadeParcelas: $.trim($("#PPC_QuantidadeParcelas").val()),
        PPC_Multiplicador: $.trim($("#PPC_Multiplicador").val()),
        PPC_PeriodoInicio: $.trim($("#PPC_PeriodoInicio").val()),
        PPC_PercentualConta: $.trim($("#PPC_PercentualConta").val()),
        PPC_ContaDestino: $.trim($("#PPC_ContaDestino").val()),
        PPC_Natureza: $.trim($("#PPC_Natureza").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          consultarViabilidadesProporcionais();
          preLoadingClose();

          $.notify(data.mensagem, "success");
        } else {
          dialogAlert(strAtencao, data.mensagem, 5, "");
        }
      },
      "json"
    );
  }
}

function excluirViabilidadesProporcionais() {
  var arrSelecionados = new Array();

  $("input[type=checkbox][name='items[]']:checked").each(function () {
    arrSelecionados.push($(this).val());
  });

  if (arrSelecionados.length > 0) {
    $("#confirm-delete").modal();
    $("#hddExcluirParametros").val(arrSelecionados);
    $("#hddExcluir").val(
      $.trim($("#hddExcluirViabilidadesProporcionais").val()) +
      "/" +
      $.trim($("#VIA_ID").val())
    );
    $("#descricaoExcluir").html(
      $("#hddLabelConfirmarSelecionados").val() +
      " " +
      arrSelecionados.length +
      " registro(s)."
    );
  } else {
    dialogAlert(
      strAtencao,
      "Selecione no minímo 1 (UMA) opção para excluir.",
      6,
      ""
    );
  }
}

function editarProporcional(viabilidadeID, proporcionalID) {
  preLoadingOpen();

  $("#btnSalvarProporcionais").html(
    "<i class='glyphicon glyphicon-refresh'></i> " +
    $("#hddLabelBtnAtualizar").val()
  );

  $.post(
    $.trim($("#hddEditarViabilidadesProporcionais").val()) +
    "/" +
    $.trim(viabilidadeID) +
    "/" +
    $.trim(proporcionalID),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#PPC_ID").val(data.arrDados[0]["PPC_ID"]);
        $("#CAX_ID2").val(data.arrDados[0]["CAX_ID"]);
        $("#PPC_Descricao").val(data.arrDados[0]["PPC_Descricao"]);
        $("#PPC_Observacao").val(data.arrDados[0]["PPC_Observacao"]);
        $("#PPC_Natureza").val(data.arrDados[0]["PPC_Natureza"]);
        $("#PPC_Multiplicador").val(
          data.arrDados[0]["PPC_Multiplicador"] +
          "|" +
          data.arrDados[0]["PPC_Tipo"]
        );
        $("#PPC_PeriodoInicio").val(data.arrDados[0]["PPC_PeriodoInicio"]);
        $("#PPC_QuantidadeParcelas").val(
          data.arrDados[0]["PPC_QuantidadeParcelas"]
        );
        $("#PPC_PercentualConta").val(data.arrDados[0]["PPC_PercentualConta"]);
        $("#PPC_ContaDestino").val(data.arrDados[0]["PPC_ContaDestino"]);
      } else {
        dialogAlert(strAtencao, data.mensagem, 6, "");
      }

      preLoadingClose();
    },
    "json"
  );
}

function consultarViabilidadesProporcionais() {
  limparViabilidadesProporcionais();

  $("#resultadoViabilidadesProporcionais").html(strCarregando);
  $.post(
    $.trim($("#hddConsultarViabilidadesProporcionais").val()),
    { VIA_ID: $.trim($("#VIA_ID").val()) },
    function (data) {
      //alert(data); return;
      $("#resultadoViabilidadesProporcionais").html(data.strHtml);

      //Visualizar informações do código proporcional selecionado
      $("a[name='visualizar[]']")
        .button()
        .click(function () {
          $.post(
            $.trim($("#hddVisualizarViabilidadesProporcionais").val()),
            {
              PPC_ID: $.trim($("#hddCodigoSelecionado").val()),
              PPC_Descricao: $.trim($("#hddNomeSelecionado").val()),
            },
            function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                dialogAlert(data.strTitulo, data.strHtml, 3);
              }
            },
            "json"
          );
        });
    },
    "json"
  );
}

function limparViabilidadesProporcionais() {
  $("#PPC_ID").val("");
  $("#CAX_ID2").val("");
  $("#PPC_Descricao").val("");
  $("#PPC_QuantidadeParcelas").val("");
  $("#PPC_PeriodoInicio").val("");
  $("#PPC_Multiplicador").val("");
  $("#PPC_PercentualConta").val("");
  $("#PPC_ContaDestino").val("");
  $("#PPC_ValorParcela").val("");
  $("#PPC_ValorTotal").val("");
  $("#PPC_Natureza").val("");
}

function editarUsuario() {
  $("#btnSalvarRapido").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#hddEditarRapido").val()),
    dataType: "json",
    cache: false,
    data: {
      SGP_Data: true,
    },
    type: "POST",
  }).success(function (data){
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3);

      setTimeout(function () {
        setInitFunctions();

        if ($.trim(data.imagem) != "") {
          $(".pop").on("click", function () {
            $(".imagepreview").attr("src", data.imagem);
            $("#modal-image").modal("show");
          });
        }

        $("#USU_AtualizarSenha").change(function () {
          if ($(this).prop("checked") == true) {
            $("#grp-passRapido").show();
            $("#grp-pass2Rapido").show();
            $("#grp-pass3Rapido").show();
          } else {
            $("#grp-passRapido").hide();
            $("#grp-pass2Rapido").hide();
            $("#grp-pass3Rapido").hide();
          }
        });

        $("#btnSalvarRapido").prop("disabled", false);
        preLoadingClose();
      }, 1000);
    }).fail(function (data) {
      $("#btnSalvarRapido").prop("disabled", false);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function exibirSenha() {
  if ($("#USU_AtualizarSenha").prop("checked") == true) {
    $("#grp-passRapido").show();
    $("#grp-pass2Rapido").show();    
  } else {
    $("#grp-passRapido").hide();
    $("#grp-pass2Rapido").hide();    
  }
}

function salvarUsuario() {
  if ($.trim($("#USU_NomeRapido").val()) == "") {
    dialogAlert(strAtencao, "Nome precisa ser informado.", 5, "");
    return;
  } else if ($("#USU_AtualizarSenha").prop("checked") == true) {
    if ($.trim($("#USU_SenhaRapido").val()) == "") {
      dialogAlert(strAtencao, "Senha precisa ser informado.", 5, "");
      return;
    } else if ($.trim($("#USU_Senha2Rapido").val()) == "") {
      dialogAlert(
        strAtencao,
        "Confirmação da senha precisa ser informado.",
        5,
        ""
      );
      return;
    } else if (
      $.trim($("#USU_SenhaRapido").val()) !=
      $.trim($("#USU_Senha2Rapido").val())
    ) {
      dialogAlert(strAtencao, "Senhas devem ser iguais.", 5, "");
      return;
    } else if ($.trim($("#SGP_PaginacaoDialog").val()) == "") {
      dialogAlert(strAtencao, "Senhas devem ser iguais.", 5, "");
      return;
    }
  }

  $("#btnSalvarRapido").prop("disabled", true);
  $("#btnSalvarRapido").html(strCarregando);

  if ($("#USU_AtualizarSenha").prop("checked") == true) {
    $("#USU_AtualizarSenha").val("S");
  } else {
    $("#USU_AtualizarSenha").val("N");
  }

  $("#frmFormularioUsuario").submit();
}

function verificaEmailUsuario(valor) {
  $.post(
    $.trim($("#hddConsultarDados").val()),
    {
      USU_ID: $.trim($("#id").val()),
      USU_Email: valor.value,
    },
    function (data) {
      //alert(data);
      if (data.sucesso == "true") {
        $("#lbl-mailRapido").removeClass("has-error").addClass("has-success");
        $("#grp-mailRapido").removeClass("has-error").addClass("has-success");
        $("#grp-mail2Rapido").removeClass("has-error").addClass("has-success");
        $("#spn-mailRapido").removeClass("has-error").addClass("has-success");
      } else {
        $("#lbl-mailRapido").removeClass("has-success ").addClass("has-error");
        $("#grp-mailRapido").removeClass("has-success").addClass("has-error");
        $("#grp-mail2Rapido").removeClass("has-success").addClass("has-error");
        $("#spn-mailRapido").removeClass("has-success").addClass("has-error");
      }

      validacaoExibirBtnSalvarUsuariosRapido();
    },
    "json"
  );
}
function consultarTerrenosFormulario() {
  preLoadingOpen();
  $("#divTerrenosFormulario").html(strCarregando);
  $("#boxFormularios").show();

  $.post(
    $.trim($("#hddTerrenosTerrenosFormulario").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      visualizar: $("#hddVisualizarTerreno").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divTerrenosFormulario").html(data.strHtml);

        setInitFunctions();

        if (data.visualizar == false) {
          /*new Sortable(document.getElementById('gridDemo'), {
          animation: 250,
          ghostClass: 'blue-background-class'
        });

        $("#gridDemo").on('drop', function (e) {
          e.preventDefault();
          e.stopPropagation();

          var arrDados = new Array();
          $('.grid-square').each(function () {
            arrDados.push(this.id);
          });

          $.ajax({
            url: $.trim($('#terrenos_terrenos_formulario_ordem').val()),
            dataType: 'json',
            cache: false,
            data: {
              TER_ID: $.trim($('#TER_ID').val()),
              SGP_Valor: arrDados
            },
            type: 'POST',
          }).success(function(data2){
            if (data2.error){
              dialogAlert(strInformacao, data2.error.msg, 6);
              return;
            }

            consultarTerrenosFormulario();
          }).fail(function(data2){
            dialogAlert(strAtencao, data2.responseText, 6);
          });
        });*/
        }
      } else {
        $("#divTerrenosFormulario").html("");
        $("#boxFormularios").hide();
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
    },
    "json"
  );
}
function consultarTerrenosEstudos() {
  preLoadingOpen();

  $("#boxTerrenosEstudos").show();
  $("#divTerrenosEstudos").html(strCarregando);

  $.post(
    $.trim($("#hddTerrenosEstudosConsultar").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      visualizar: $("#hddVisualizarTerreno").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.totalRegistros > 0) {
          $("#divTerrenosEstudos").html(data.strHtml);
        } else {
          $("#divTerrenosEstudos").html(data.strHtml);
          $("#boxTerrenosEstudos").hide();
        }
      } else {
        $("#divTerrenosEstudos").html("");
        $("#boxTerrenosEstudos").hide();
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
    },
    "json"
  );
}

function consultarTerrenosEstudoNovo() {
  preLoadingOpen();

  $("#boxTerrenosEstudoNovo").show();
  $("#divTerrenosEstudoNovo").html(strCarregando);

  $.post(
    $.trim($("#hddTerrenosEstudoNovoConsultar").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      visualizar: $("#hddVisualizarTerreno").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.totalRegistros > 0) {
          $("#divTerrenosEstudoNovo").html(data.strHtml);
        } else {
          $("#divTerrenosEstudoNovo").html(data.strHtml);
          $("#boxTerrenosEstudoNovo").hide();
        }
      } else {
        $("#divTerrenosEstudoNovo").html("");
        $("#boxTerrenosEstudoNovo").hide();
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
    },
    "json"
  );
}

function consultarTerrenosViabilidades() {
  preLoadingOpen();

  $("#boxViabilidades").show();
  $("#divTerrenosViabilidades").html(strCarregando);

  $.post(
    $.trim($("#hddViabilidadesTerrenosConsultar").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      visualizar: $("#hddVisualizarTerreno").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.totalRegistros > 0) {
          $("#divTerrenosViabilidades").html(data.strHtml);
        } else {
          $("#divTerrenosViabilidades").html(data.strHtml);
          $("#boxViabilidades").hide();
        }
      } else {
        $("#boxViabilidades").hide();
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
    },
    "json"
  );
}

function salvarViabilidadesFinanceiro() {
 $("#btnAdicionarFinanceiro").prop("disabled", true);
  var strLabel = $("#btnAdicionarFinanceiro").html();

  $("#btnAdicionarFinanceiro").html(strCarregando);

  $.post(
    $.trim($("#hddSalvarViabilidadesFinanceiro").val()),
    {
      VIA_ID: $.trim($("#VIA_ID").val()),
      FIN_PercentualValorPresente: $.trim(
        $("#FIN_PercentualValorPresente").val()
      ),
      FIN_PercentualTaxaAdministrativa: $.trim(
        $("#FIN_PercentualTaxaAdministrativa").val()
      ),
      FIN_PercentualComissao: $.trim($("#FIN_PercentualComissao").val()),
      FIN_PercentualImposto: $.trim($("#FIN_PercentualImposto").val()),
      FIN_FlagCalculoImposto: $.trim($("#FIN_FlagCalculoImposto").val()),
      FIN_PercentualPermuta: $.trim($("#FIN_PercentualPermuta").val()),
      FIN_CarenciaPermuta: $.trim($("#FIN_CarenciaPermuta").val()),
      FIN_FlagPermutaComissao: $.trim($("#FIN_FlagPermutaComissao").val()),
      FIN_FlagPermutaImposto: $.trim($("#FIN_FlagPermutaImposto").val()),
      FIN_PercentualObraFinanciado: $.trim(
        $("#FIN_PercentualObraFinanciado").val()
      ),
      FIN_TaxaJurosFinanciamento: $.trim(
        $("#FIN_TaxaJurosFinanciamento").val()
      ),
      FIN_FlagCalculoLiberacao: $.trim($("#FIN_FlagCalculoLiberacao").val()),
      FIN_PercentualMinimoLiberacao: $.trim(
        $("#FIN_PercentualMinimoLiberacao").val()
      ),
      FIN_CarenciaAmortizacao: $.trim($("#FIN_CarenciaAmortizacao").val()),
      FIN_QuantidadeParcelasAmortizacao: $.trim(
        $("#FIN_QuantidadeParcelasAmortizacao").val()
      ),
      FIN_FlagTemJurosCarencia: $.trim($("#FIN_FlagTemJurosCarencia").val()),
      FIN_MCMV_PercentualFinanciado: $.trim(
        $("#FIN_MCMV_PercentualFinanciado").val()
      ),
      FIN_MCMV_PercentualObra: $.trim($("#FIN_MCMV_PercentualObra").val()),
      FIN_MCMV_PeriodoAprovacao: $.trim($("#FIN_MCMV_PeriodoAprovacao").val()),
      FIN_MCMV_PercentualHabitese: $.trim(
        $("#FIN_MCMV_PercentualHabitese").val()
      ),
      FIN_FracaoTerreno: $.trim($("#FIN_FracaoTerreno").val()),
      FIN_PercentualPJ: $.trim($("#FIN_PercentualPJ").val()),
      FIN_PercentualJurosPJ: $.trim($("#FIN_PercentualJurosPJ").val()),
      FIN_PercentualMinimoVendaPJ: $.trim(
        $("#FIN_PercentualMinimoVendaPJ").val()
      ),
      FIN_InflacaoTerreno: $.trim($("#FIN_InflacaoTerreno").val()),
      FIN_InflacaoVendas: $.trim($("#FIN_InflacaoVendas").val()),
      FIN_InflacaoObra: $.trim($("#FIN_InflacaoObra").val()),
      FIN_InflacaoItens: $.trim($("#FIN_InflacaoItens").val()),
    },
    function (data) {
  
      if (data.sucesso == "true") {
        $.notify(data.mensagem, "success");
      } else {
        $.notify(data.mensagem, "error");
      }

      $("#btnAdicionarFinanceiro").html(strLabel);
      $("#btnAdicionarFinanceiro").prop("disabled", false);
    },"json"
  );
  //}
}

function consultarViabilidadesLog() {
  $("#resultadoViabilidadesLog").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddConsultarViabilidadesLog").val()),
    dataType: "json",
    cache: false,
    data: {
      VIA_ID: $.trim($("#VIA_ID").val())
    },
    type: "POST",
  }).success(function (data) {
    if (data.error) {
      dialogAlert(strAtencao, data.error.msg, 6);
      return;
    }

    $("#resultadoViabilidadesLog").html(data.strHtml);

  }).fail(function (data) {
    $("#resultadoViabilidadesLog").html('');
    dialogAlert(strAtencao, data.responseText, 6);
  });

  /*$.post(
    $.trim($("#hddConsultarViabilidadesLog").val()),
    { VIA_ID: $.trim($("#VIA_ID").val()) },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#resultadoViabilidadesLog").html(data.strHtml);
      } else {
        $.notify(data.mensagem, "error");
        $("#resultadoViabilidadesLog").html(data.mensagem);
      }
    },
    "json"
  );*/
}

function esqueciSenha() {
  $.post(
    $.trim($("#hddEsqueciSenha").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2("Esqueci senha", data.strHtml, 3, "", true, false);
        // dialogAlert2(strTitulo, strMensagem, strTipo, htmlID = '', closer = true, init = true)
        setTimeout(function () {
          $("#USU_Login2").focus();
        }, 500);
      }
    },
    "json"
  );
}

function esqueciSenhaCorretor() {
  $.post(
    $.trim($("#hddEsqueciSenhaCorretor").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2("Portal do Corretor - Esqueci senha", data.strHtml, 3);
      }
    },
    "json"
  );
}

function esqueciSenhaEntidades() {
  $.post(
    $.trim($("#hddEntidadesEsqueciSenha").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2("Portal do Cliente - Esqueci senha ", data.strHtml, 3);
      }
      return;
    },
    "json"
  );
}

function enterEsqueciSenhaEntidades(e) {
  if (e.keyCode == 13) {
    enviarEsqueciSenhaEntidades();
  }
}

function enviarEsqueciSenhaEntidades() {
  if ($.trim($("#USU_Login2").val()) == "") {
    $.notify("E-mail precisa ser informado.", "warn");
    return;
  } else {
    $("#USU_Login2").prop("disabled", true);

    $.post(
      $.trim($("#hddEntidadesEsqueciSenhaEnvio").val()),
      { USU_Email: $.trim($("#USU_Login2").val()) },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");
        } else {
          $.notify(data.mensagem, "error");
        }
        $(".modal").modal("hide");
        $("#USU_Login2").prop("disabled", false);
        return;
      },
      "json"
    );
  }
}

function enviarEsqueciSenha() {
  if ($.trim($("#USU_Login2").val()) == "") {
    $.notify("E-mail precisa ser informado.", "warn");
    return;
  } else {
    $("#USU_Login2, .btn-formulario").prop("disabled", true);

    $.ajax({
      url: $.trim($("#hddEsqueciSenhaEnvio").val()),
      dataType: "json",
      cache: false,
      data: {
        USU_Email: $.trim($("#USU_Login2").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#USU_Login2, .btn-formulario").prop("disabled", false);

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $(".modal").modal("hide");
        $.notify(data.mensagem, "success");
      })
      .fail(function (data) {
        $("#USU_Login2, .btn-formulario").prop("disabled", false);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function enviarEsqueciSenhaCorretor() {
  if ($.trim($("#USU_Login2").val()) == "") {
    $.notify("E-mail precisa ser informado.", "warn");
    return;
  } else {
    $("#USU_Login2").prop("disabled", true);

    $.post(
      $.trim($("#hddEsqueciSenhaEnvioCorretor").val()),
      { USU_Email: $.trim($("#USU_Login2").val()) },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");
        } else {
          $.notify(data.mensagem, "error");
        }

        $(".modal").modal("hide");
        $("#USU_Login2").prop("disabled", false);
        return;
      },
      "json"
    );
  }
}

function enterValidarNovaSenha(e) {
  if (e.keyCode == 13) {
    validarNovaSenha();
  }
}

function enterValidarNovaSenhaCorretor(e) {
  if (e.keyCode == 13) {
    validarNovaSenhaCorretor();
  }
}

function validarNovaSenha() {
  if ($.trim($("#USU_Senha").val()) == "") {
    $.notify("Senha precisa ser informada.", "warn");
  } else if ($.trim($("#USU_Senha2").val()) == "") {
    $.notify("Confirmação da senha precisa ser informada.", "warn");
  } else if ($("#USU_Senha").val() != $("#USU_Senha2").val()) {
    $.notify("Senhas precisam ser iguais.", "warn");
  } else {
    $("#btnEnviar").prop("disabled", true);
    var strLabel = $("#btnEnviar").html();
    $("#btnEnviar").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddEsqueciAtualizar").val()),
      dataType: "json",
      cache: false,
      type: "POST",
      data: {
        USU_Cript: $.trim($("#hddCript").val()),
        USU_Senha: $.trim($("#USU_Senha").val()),
      },
    })
      .success(function (data) {
        $("#btnEnviar").html(strLabel);
        $("#btnEnviar").prop("disabled", false);

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6, "", false);
          return;
        }

        $.notify(data.mensagem, "success");

        setTimeout(function () {
          redir(data.redir, "parent");
        }, 2000);
      })
      .fail(function (data) {
        $("#btnEnviar").html(strLabel);
        $("#btnEnviar").prop("disabled", false);
        dialogAlert(strAtencao, data.responseText, 6, "", false);
      });
  }
}

function validarNovaSenhaEntidades() {
  if ($.trim($("#USU_Senha").val()) == "") {
    $.notify("Senha precisa ser informada.", "warn");
  } else if ($.trim($("#USU_Senha2").val()) == "") {
    $.notify("Confirmação da senha precisa ser informada.", "warn");
  } else if ($("#USU_Senha").val() != $("#USU_Senha2").val()) {
    $.notify("Senhas precisam ser iguais.", "warn");
  } else {
    $("#btnEnviar").prop("disabled", true);
    var strLabel = $("#btnEnviar").html();
    $("#btnEnviar").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddEsqueciAtualizarEntidades").val()),
      dataType: "json",
      cache: false,
      type: "POST",
      data: {
        USU_Cript: $.trim($("#hddCript").val()),
        USU_Senha: $.trim($("#USU_Senha").val()),
      },
    })
      .success(function (data) {
        $("#btnEnviar").html(strLabel);
        $("#btnEnviar").prop("disabled", false);

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        setTimeout(function () {
          redir(data.redir, "parent");
        }, 2000);
      })
      .fail(function (data) {
        $("#btnEnviar").html(strLabel);
        $("#btnEnviar").prop("disabled", false);
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function enterValidarNovaSenhaEntidades(e) {
  if (e.keyCode == 13) {
    validarNovaSenhaEntidades();
  }
}

function validarNovaSenhaCorretor() {
  if ($.trim($("#USU_Senha").val()) == "") {
    $.notify("Senha precisa ser informada.", "warn");
  } else if ($.trim($("#USU_Senha2").val()) == "") {
    $.notify("Confirmação da senha precisa ser informada.", "warn");
  } else if ($("#USU_Senha").val() != $("#USU_Senha2").val()) {
    $.notify("Senhas precisam ser iguais.", "warn");
  } else {
    $.post(
      $.trim($("#hddEsqueciAtualizarCorretor").val()),
      {
        USU_Cript: $.trim($("#hddCript").val()),
        USU_Senha: $.trim($("#USU_Senha").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");

          setTimeout(function () {
            redir(data.redir, "parent");
          }, 2000);
        }
      },
      "json"
    );
  }
}

function enterEsqueciSenha(e) {
  if (e.keyCode == 13) {
    enviarEsqueciSenha();
  }
}

function enterEsqueciSenhaCorretor(e) {
  if (e.keyCode == 13) {
    enviarEsqueciSenhaCorretor();
  }
}

function salvarEmpresas() {
  if ($.trim("#EMP_CPFCNPJ") == "") {
    $.notify("CPF/CNPJ precisa ser informado.", "warn");
  } else if ($.trim("#EMP_RazaoSocial") == "") {
    $.notify("Nome/Razão Social precisa ser informado.", "warn");
  } else if ($.trim("#EMP_NomeFantasia") == "") {
    $.notify("Apelido/Fantasia precisa ser informado.", "warn");
  } else if ($.trim("#UF_ID") == "") {
    $.notify("Estado precisa ser informado.", "warn");
  } else {
    var arrUsuarios = new Array();
    $("select[name='USU_ID[]'] option:selected").each(function () {
      arrUsuarios.push($(this).val());
    });

    if (arrUsuarios.length == 0) {
      $.notify("Selecione no mínimo um usuário para acesso a empresa.", "warn");
      return;
    }

    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnSalvar").html();
    $("#btnSalvar").html(strCarregando);
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#empresas_salvar").val()),
      dataType: "json",
      cache: false,
      data: $("#frmFormulario").serialize(),
      type: "POST",
    }).success(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvar").html(strLabel);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        setTimeout(function () {
          redir(data.redir, "parent");
        }, 1500);
      }).fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvar").html(strLabel);
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function verificaBtnEmpresas() {
  if ($("#grp-cpfcnpj, #grp-cpfcnpj2").attr("class").includes("has-success")) {
    $("#btnSalvar").prop("disabled", false);
  } else {
    $("#btnSalvar").prop("disabled", true);
  }
}

function validarEmail(valor) {
  if ($.trim(valor) != "") {
    $.post(
      $.trim($("#hddValidarEmail").val()),
      { strEmail: $.trim(valor) },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#lbl-email").removeClass("has-error").addClass("has-success");
          $("#grp-email").removeClass("has-error").addClass("has-success");
        } else {
          $("#lbl-email").removeClass("has-success").addClass("has-error");
          $("#grp-email").removeClass("has-success").addClass("has-error");

          $.notify(data.mensagem, "error");
        }

        verificaBtnEmpresas();
      },
      "json"
    );
  } else {
    $.notify("E-mail precisa ser informado.", "error");
  }
}
var resultValidaCPFCNPJ = false;
function validarCPFCNPJ(valor){
  if ($.trim(valor) != "" && $.trim($('#EMP_TipoPessoa').val()) != $.trim($("#hddFlagPessoaPassaporte").val())) {
    $.post(
      $.trim($("#hddValidarCPFCNPJ").val()),
      { strCPFCNPJ: $.trim(valor) },
      function (data) {
        //alert(data); return;

        if (data.sucesso == "true") {

          resultValidaCPFCNPJ = true;

          $("#lblCPFCNPJ, #lblCPFCNPJ2")
            .removeClass("has-error")
            .addClass("has-success");
          $("#grp-cpfcnpj, #grp-cpfcnpj2")
            .removeClass("has-error")
            .addClass("has-success");

        } else {

          resultValidaCPFCNPJ = false; 

          $("#lblCPFCNPJ, #lblCPFCNPJ2")
            .removeClass("has-success")
            .addClass("has-error");
          $("#grp-cpfcnpj, #grp-cpfcnpj2")
            .removeClass("has-success")
            .addClass("has-error");

          $.notify(data.mensagem, "error");

        }

        verificaBtnEmpresas();
      },
      "json"
    );
  } else {
    resultValidaCPFCNPJ = true; 
    $("#lblCPFCNPJ, #lblCPFCNPJ2").removeClass("has-success has-error");
    $("#grp-cpfcnpj, #grp-cpfcnpj2").removeClass("has-success has-error");

    if ($.trim($('#EMP_TipoPessoa').val()) != $.trim($("#hddFlagPessoaPassaporte").val())){
      $.notify("CPF/CNPJ precisa ser informado.", "error");
    }    
  }
}

function formAdicionarBlocos() {
  $.post(
    $.trim($("#hddNovoEstruturaBloco").val()),
    {
      EST_ID: $.trim($("#EST_ID").val()),
      BLO_ID: $.trim($("#hddCodigoSelecionado").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert("Estruturas Blocos", data.strHtml, 3);

        setTimeout(function () {
          if ($.trim($("#hddCodigoSelecionado").val()) == "") {
            limparEstruturasBloco();
          } else {
            $("#hddCodigoSelecionado").val("");
          }
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function limparEstruturasBloco() {
  $(
    "#BLO_ID, #BLO_Descricao, #BLO_Tipologia, #BLO_DataLancamento, #BLO_DataEntrega"
  ).val("");
}

function consultarEstuturasBloco() {
  $("#carregarBlocos").html(strCarregando);

  $.post(
    $.trim($("#hddConsultarEstruturaBloco").val()),
    {
      ROT_ID: $.trim($("#ROT_ID2").val()),
      EST_ID: $.trim($("#EST_ID").val()),
    },
    function (data) {
      if (data.sucesso == "true") {
        $("#carregarBlocos").html(data.strHtml);

        $("table.display").DataTable({
          bPaginate: false,
          paginate: false,
          responsive: true,
          paging: false,
          lengthChange: true,
          searching: true,
          ordering: true,
          info: false,
          autoWidth: true,
          scrollX: true,
          //'dom'		  : 'lBfrtip',
          order: [],
          iDisplayLength: 1000000000000,
          language: {
            url: $("#hddFile").val(),
          },
        });
      } else {
        $("#carregarBlocos").html("");
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarEstruturasBloco() {
  if ($.trim($("#BLO_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "error");
  } else if ($.trim($("#BLO_Tipologia").val()) == "") {
    $.notify("Tipologia precisa ser informada.", "error");
  } else if (
    $.trim($("#BLO_DataLancamento").val()) == "" ||
    $.trim($("#BLO_DataLancamento").val()) == "__/__/____"
  ) {
    $.notify("Data de lançmento precisa ser informada.", "error");
  } else if (
    $.trim($("#BLO_DataEntrega").val()) == "" ||
    $.trim($("#BLO_DataEntrega").val()) == "__/__/____"
  ) {
    $.notify("Data de entrega precisa ser informada.", "error");
  } else if (
    $.trim($("#BLO_DataInicioJuros").val()) == "" ||
    $.trim($("#BLO_DataInicioJuros").val()) == "__/__/____"
  ) {
    $.notify("Data de início juros precisa ser informada.", "error");
  } else {
    preLoadingOpen();

    $.post(
      $.trim($("#hddSalvarEstruturaBloco").val()),
      {
        ROT_ID: $.trim($("#ROT_ID2").val()),
        BLO_ID: $.trim($("#BLO_ID").val()),
        EST_ID: $.trim($("#EST_ID").val()),
        BLO_Descricao: $.trim($("#BLO_Descricao").val()),
        BLO_Tipologia: $.trim($("#BLO_Tipologia").val()),
        BLO_DataLancamento: $.trim($("#BLO_DataLancamento").val()),
        BLO_DataEntrega: $.trim($("#BLO_DataEntrega").val()),
        BLO_DataInicioJuros: $.trim($("#BLO_DataInicioJuros").val()),
      },
      function (data) {
        //alert(data); return;
        preLoadingClose();
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");

          setTimeout(function () {
            redir("", "parent");
          }, 1000);
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  }
}

function formAdicionarUnidades(blocoID, blocoDescricao, strTipologia) {
  $.post(
    $.trim($("#hddNovoEstruturaUnidade").val()),
    {
      UNI_ID: $.trim($("#hddCodigoSelecionado").val()),
      EST_ID: $.trim($("#EST_ID").val()),
      BLO_ID: $.trim(blocoID),
      BLO_Descricao: $.trim(blocoDescricao),
      strTipologia: strTipologia,
      strCopiar: $.trim($("#hddExecutar").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(
          "Adicionar Estruturas Unidades (Bloco: " + blocoDescricao + ")",
          data.strHtml,
          3
        );

        setTimeout(function () {
          $(
            "#divVagas, #divDormitorios, #divAndar, #divUnidadeVinculo, #btnAdicionarCadastrosAuxiliares"
          ).hide();

          if (
            $.trim($("#BLO_Tipologia").val()) == "V" ||
            $.trim($("#BLO_Tipologia").val()) == "P"
          ) {
            $("#divUnidadeVinculo").show();
          }

          $("#BLO_Tipologia").change(function () {
            $(
              "#divVagas, #divDormitorios, #divAndar, #divUnidadeVinculo"
            ).hide();
            $(
              ".campos-novos-private-vaga, .campos-novos, .campos-novos-residencial"
            ).removeClass("show hide");

            if ($.trim(this.value) == "V" || $.trim(this.value) == "P") {
              $("#divUnidadeVinculo").show();
              $(".campos-novos-private-vaga").addClass("show");

              if ($.trim(this.value) != "V") {
                $(".campos-novos").addClass("show");
              }
            } else {
              if ($.trim(this.value) == "R") {
                $(".campos-novos-residencial").addClass("show");
              } else if ($.trim(this.value) != "V") {
                $(".campos-novos").addClass("show");
              }

              $("#divVagas, #divDormitorios, #divAndar").show();
            }
          });

          $("#BLO_Tipologia").trigger("change");
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarEstruturasUnidade() {
  if (
    $.trim($("#UNI_Situacao").val()) == "" &&
    $("#UNI_Situacao").prop("disabled") == false
  ) {
    $.notify("Situação precisa ser informada.", "error");
  } else if ($.trim($("#UNI_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "error");
  } else if ($.trim($("#BLO_Tipologia").val()) == "") {
    $.notify("Tipologia precisa ser informada.", "error");
  } else if (
    $("#UNI_Situacao:not(:disabled)").val() &&
    $.trim($("#UNI_Situacao").val()) == ""
  ) {
    $.notify("Situação precisa ser informada.", "error");
  } else {
    $("#btnSalvarDialog").prop("disabled", true);
    var strLabel = $("#btnSalvarDialog").html();
    $("#btnSalvarDialog").html(strCarregando);

    var arrDados = new FormData();
    var strTipologia = $.trim($("#BLO_Tipologia").val());

    if ($("#SGP_TipoCadastro").val() != undefined) {
      arrDados.append("SGP_TipoCadastro", $.trim($("#SGP_TipoCadastro").val()));
    }

    if ($("#SGP_Quantidade").val() != undefined) {
      arrDados.append("SGP_Quantidade", $.trim($("#SGP_Quantidade").val()));
    }

    arrDados.append("EST_ID", $.trim($("#EST_ID").val()));
    arrDados.append("BLO_ID", $.trim($("#BLO_ID").val()));
    arrDados.append("UNI_ID", $.trim($("#UNI_ID").val()));
    arrDados.append("CAX_ID", $.trim($("#CAX_ID3").val()));
    arrDados.append("UNI_Descricao", $.trim($("#UNI_Descricao").val()));
    arrDados.append("UNI_Tipologia", strTipologia);
    arrDados.append("UNI_AreaPrivida", $.trim($("#UNI_AreaPrivida").val()));
    arrDados.append(
      "UNI_AreaPrividaAnterior",
      $.trim($("#UNI_AreaPrividaAnterior").val())
    );
    arrDados.append("UNI_AreaComum", $.trim($("#UNI_AreaComum").val()));
    arrDados.append("UNI_FracaoIdeal", $.trim($("#UNI_FracaoIdeal").val()));
    arrDados.append("UNI_Matricula", $.trim($("#UNI_Matricula").val()));
    arrDados.append("UNI_Situacao", $.trim($("#UNI_Situacao").val()));
    arrDados.append(
      "UNI_AreaComumCoberta",
      $.trim($("#UNI_AreaComumCoberta").val())
    );
    arrDados.append(
      "UNI_AreaComumDescoberta",
      $.trim($("#UNI_AreaComumDescoberta").val())
    );
    arrDados.append(
      "UNI_AreaComumTotal",
      $.trim($("#UNI_AreaComumTotal").val())
    );
    arrDados.append(
      "UNI_AreaPrivativaFloreiras",
      $.trim($("#UNI_AreaPrivativaFloreiras").val())
    );
    arrDados.append("UNI_QuotaTerreno", $.trim($("#UNI_QuotaTerreno").val()));
    arrDados.append("UNI_AreaGaragem", $.trim($("#UNI_AreaGaragem").val()));
    arrDados.append(
      "UNI_AreaAcessoriaDescoberta",
      $.trim($("#UNI_AreaAcessoriaDescoberta").val())
    );
    arrDados.append("UNI_Laje", $.trim($("#UNI_Laje").val()));
    arrDados.append("UNI_Subsolo", $.trim($("#UNI_Subsolo").val()));
    arrDados.append("UNI_Observacoes", $.trim($("#UNI_Observacoes").val()));
    arrDados.append("SGP_Copiar", $.trim($("#hddCopiar").val()));

    if (strTipologia == "V" || strTipologia == "P") {
      arrDados.append("UNI_Vinculo_ID", $.trim($("#UNI_ID2").val()));
    } else {
      arrDados.append("UNI_Vagas", $.trim($("#UNI_Vagas").val()));
      arrDados.append("UNI_Dormitorios", $.trim($("#UNI_Dormitorios").val()));
      arrDados.append("UNI_Andar", $.trim($("#UNI_Andar").val()));
    }

    $.ajax({
      url: $.trim($("#hddSalvarEstruturaUnidade").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "post",
      success: function (data) {
        $("#btnSalvarDialog").prop("disabled", false);
        $("#btnSalvarDialog").html(strLabel);

        if (data.error) {
          $("#consultar-dados").html("");
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        $(
          "#UNI_Situacao, #SGP_TipoCadastro, #SGP_Quantidade, #BLO_Tipologia, #CAX_ID3, #UNI_Descricao, #UNI_AreaPrivida, #UNI_AreaComum, #UNI_FracaoIdeal, #UNI_Matricula, #UNI_ID2, #UNI_Vagas, #UNI_Dormitorios, #UNI_Andar"
        ).val("");
        $(
          "#UNI_Situacao, #SGP_TipoCadastro, #SGP_Quantidade, #BLO_Tipologia, #CAX_ID3"
        ).trigger("chosen:updated");
        consultarEstuturasBloco();
      },
    }).fail(function (data) {
      $("#btnSalvarDialog").prop("disabled", false);
      $("#btnSalvarDialog").html(strLabel);

      dialogAlert(strAtencao, data.responseText, 6);
    });
  }
}

function limparEstruturasUnidade() {
  $(
    "#UNI_Descricao, #UNI_AreaPrivida, #UNI_AreaComum, #UNI_FracaoIdeal, #BLO_Tipologia, #UNI_Matricula, #UNI_Vagas, #UNI_Dormitorios, #UNI_Andar, #UNI_Situacao, #UNI_AreaComumCoberta, #UNI_AreaComumDescoberta, #UNI_AreaComumTotal, #UNI_AreaPrivativaFloreiras, #UNI_QuotaTerreno, #UNI_AreaGaragem, #UNI_AreaAcessoriaDescoberta, #UNI_Laje, #UNI_Subsolo, #UNI_Observacoes"
  ).val("");
}

function consultarEstuturasUnidade() {
  $.post(
    $.trim($("#hddConsultarEstruturaUnidade").val()),
    {
      ROT_ID: $.trim($("#ROT_ID2").val()),
      EST_ID: $.trim($("#EST_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#carregarUnidades").html(data.strHtml);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarPlanoFinanceiro() {
  if ($.trim($("#PLF_Conta").val()) == "") {
    $.notify("Conta precisa ser informada.", "error");
  } else if ($.trim($("#PLF_Tipo").val()) == "") {
    $.notify("Tipo da conta precisa ser informada.", "error");
  } else if ($.trim($("#PLF_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "error");
  } else {
    $("#btnSalvar").prop("disabled", true);

    var strRedutivel = "N";

    if ($("#PLF_Redutivel").prop("checked") == true) strRedutivel = "S";

    $.post(
      $.trim($("#hddPlanoFinanceiroAdicionar").val()),
      {
        ROT_ID: $.trim($("#ROT_ID2").val()),
        PLF_Conta: $.trim($("#PLF_Conta").val()),
        PLF_Tipo: $.trim($("#PLF_Tipo").val()),
        PLF_Descricao: $.trim($("#PLF_Descricao").val()),
        PLF_Redutivel: strRedutivel,
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          redir(data.redir, "parent");
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  }
}

function salvarValoresIndexadores() {

  if ($.trim($("#VAL_DataIndexador").val()) == "") {
    $.notify("Data do indexador precisa ser informada.", "warn");
  } else if ($.trim($("#VAL_ValorIndexador").val()) == "") {
    $.notify("Valor do indexador precisa ser informado.", "warn");
  } else {
    $("#btnAdicionarValores").prop("disabled", true);
    var strLabel = $("#btnAdicionarValores").html();
    $("#btnAdicionarValores").html(strCarregando);

    $.ajax({
      url: $.trim($('#indexadores_salvar_item').val()),
      dataType: 'json',
      cache: false,
      data: {
        VAL_ID: $.trim($("#hddValorID").val()),
        IND_ID: $.trim($("#hddIndex").val()),
        VAL_DataIndexador: $.trim($("#VAL_DataIndexador").val()),
        VAL_ValorIndexador: $.trim($("#VAL_ValorIndexador").val()),
      },
      type: 'POST',
    }).success(function(data){
      $("#btnAdicionarValores").prop("disabled", false);
      $("#btnAdicionarValores").html(strLabel);
  
      if (data.error){
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      limparValoresIndexadores();
      consultarValoresIndexadores();
      $.notify(data.mensagem, "success");
  
    }).fail(function(data){
      $("#btnAdicionarValores").prop("disabled", false);
      $("#btnAdicionarValores").html(strLabel);  
      dialogAlert(strAtencao, data.responseText, 6);
    });
  }
}

function consultarValoresIndexadores() {
  $("#resultadoIndexadores").html(strCarregando);

  $.post(
    $.trim($("#hddIndexConsultarItem").val()),
    { IND_ID: $.trim($("#hddIndex").val()) },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#resultadoIndexadores").html(data.strHtml);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function limparValoresIndexadores() {
  $("#VAL_DataIndexador").val("");
  $("#VAL_ValorIndexador").val("");
  $("#hddValorID").val("");
}

function editarValoresIndexadores(valorID) {
  preLoadingOpen();

  $("#btnAdicionarValores").html(
    "<i class='glyphicon glyphicon-refresh'></i> " +
    $("#hddLabelBtnAtualizar").val()
  );

  $.post(
    $.trim($("#hddIndexEditarItem").val()) + "/" + $.trim(valorID),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#hddValorID").val(data.arrDados[0]["VAL_ID"]);
        $("#VAL_DataIndexador").val(data.arrDados[0]["VAL_DataIndexador"]);
        $("#VAL_ValorIndexador").val(data.arrDados[0]["VAL_ValorIndexador"]);
        $("#VAL_PercentualIndexador").val(
          data.arrDados[0]["VAL_PercentualIndexador"]
        );
        $("#VAL_PercentualAcumulado").val(
          data.arrDados[0]["VAL_PercentualAcumulado"]
        );
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
    },
    "json"
  );
}

function calcularIndexadorPercentual() {
  if (parseFloat($("#VAL_ValorIndexador").val()) > 0) {
    $.post(
      $.trim($("#hddIndexCalcularPercentualItem").val()),
      {
        IND_ID: $.trim($("#hddIndex").val()),
        VAL_ValorIndexador: $.trim($("#VAL_ValorIndexador").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#VAL_PercentualIndexador").val(data.douPercentual);
          $("#VAL_PercentualAcumulado").val(data.douPercentualAcumulado);

          $.notify("Percentual calculado", "success");
        }
      },
      "json"
    );
  }
}

function calcularIndexadorValor() {
  $.post(
    $.trim($("#hddIndexCalcularValorItem").val()),
    {
      IND_ID: $.trim($("#hddIndex").val()),
      VAL_PercentualIndexador: $.trim($("#VAL_PercentualIndexador").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#VAL_PercentualIndexador").val(data.douPercentual);
        $("#VAL_PercentualAcumulado").val(data.douPercentualAcumulado);

        $.notify("Percentual calculado", "success");
      }
    },
    "json"
  );
}

function calculateAndDisplayRoute(directionsService, directionsDisplay) {
  var selectedMode = document.getElementById("selecionarModo").value;

  directionsService.route(
    {
      origin: { lat: 37.77, lng: -122.447 }, // Haight.
      destination: { lat: 37.768, lng: -122.511 }, // Ocean Beach.
      // Note that Javascript allows us to access the constant
      // using square brackets and a string value as its
      // "property."
      travelMode: google.maps.TravelMode[selectedMode],
    },
    function (response, status) {
      if (status == "OK") {
        directionsDisplay.setDirections(response);
      } else {
        $.notify("Erro na requisição solicitada.", "warn");
        //window.alert('Directions request failed due to ' + status);
      }
    }
  );
}

function salvarTerrenoCorretores() {
  if ($.trim($("#COR_Descricao").val()) == "") {
    $.notify("Descrição do terreno precisa ser informada.", "warn");
  } else if ($.trim($("#COR_ValorOferta").val()) == "") {
    $.notify("Valor da oferta do terreno precisa ser informada.", "warn");
  } else if ($.trim($("#COR_TamanhoTerreno").val()) == "") {
    $.notify("Tamanho do terreno precisa ser informada.", "warn");
  } else if ($.trim($("#COR_ValorM2Terreno").val()) == "") {
    $.notify(
      "Valor por metro quadrado do terreno precisa ser informado.",
      "warn"
    );
  } else if ($.trim($("#coordenadas").val()) == "") {
    $.notify("Insira o polígono no mapa para salvar seu Terreno.", "warn");
  } else {
    $("#frmFormulario").submit();
  }
}

function consultarTerrenosCorretoresDocumentos() {
  preLoadingOpen();

  $("#divDocumentos").html(strCarregando);

  $.post(
    $.trim($("#hddConsultarCorretoresTerrenos").val()),
    {
      COR_ID: $.trim($("#COR_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();
        $("#divDocumentos").html(data.strHtml);
      } else {
        preLoadingClose();
      }
    },
    "json"
  );
}

function salvarTerrenosCorretoresDocumentos() {
  if ($.trim($("#DOC_Descricao").val()) == "") {
    $.notify("Descrição do documento precisa ser informado.", "warn");
  } else if ($.trim($("#DOC_Anexo").val()) == "") {
    $.notify("Anexo do documento precisa ser informado.", "warn");
  } else {
    $("#formDocumentos").submit();
  }
}

function consultarTerrenosCorretoresStatus() {
  preLoadingOpen();

  $("#divStatus").html(strCarregando);

  $.post(
    $.trim($("#corretores_terrenos_status").val()),
    {
      COR_ID: $.trim($("#COR_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();
        $("#divStatus").html(data.strHtml);
      } else {
        $("#divStatus").html("");
        preLoadingClose();
      }
    },
    "json"
  );
}

function infoCorretor(texto) {
  $.notify(texto, "success");
}

function confirmarCaptura(strAcao) {
  $("#linkConfirmar").html(strCarregando);
  preLoadingOpen();

  $.post(
    $.trim(strAcao),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();

        redir(data.redir, "parent");
      } else {
        preLoadingClose();

        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function infoUsuarios() {
  preLoadingOpen();

  $.post(
    $.trim($("#hddUsuariosInfo").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();
        dialogAlert(data.strTitulo, data.strHtml, 2);
      } else {
        preLoadingClose();
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function infoAcessos() {
  preLoadingOpen();

  $.post(
    $.trim($("#hddAcessosInfo").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();
        dialogAlert(data.strTitulo, data.strHtml, 4);
      } else {
        preLoadingClose();
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function infoModulos() {
  preLoadingOpen();

  $.post(
    $.trim($("#hddModulosInfo").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();
        dialogAlert(data.strTitulo, data.strHtml, 5);
      } else {
        preLoadingClose();
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function infoGruposEmpresas() {
  preLoadingOpen();

  $.post(
    $.trim($("#hddGruposEmpresasInfo").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        preLoadingClose();
        dialogAlert(data.strTitulo, data.strHtml, 6);
      } else {
        preLoadingClose();
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

/////////////////// FILTRO PADRÃO DIALOG
function filtroPesquisar(acao, par1 = "", par2 = "") {
  if (acao == "empresas") {
    filtroPesquisarEmpresas();
  } else if (acao == "empresas2") {
    filtroPesquisarEmpresas2();
  } else if (acao == "projetos") {
    filtroPesquisarProjetos();
  } else if (acao == "insumos") {
    filtroPesquisarInsumos();
  } else if (acao == "fornecedorescotacoes") {
    filtroPesquisarFornecedoresCotacoes();
  } else if (acao == "fornecedores") {
    filtroPesquisarFornecedores();
  } else if (acao == "beneficiarios") {
    filtroPesquisarBeneficiarios();
  } else if (acao == "itens_contratos") {
    filtroPesquisarItensContratos();
  } else if (acao == "itens_medicoes") {
    filtroPesquisarItensMedicoes();
  } else if (acao == "itens_documentos_pedidos") {
    filtroPesquisarItensDocumentosPedidos();
  } else if (acao == "itens_documentos_medicoes") {
    filtroPesquisarItensDocumentosMedicoes();
  } else if (acao == "contas_pagar") {
    filtroPesquisarContasPagar();
  } else if (acao == "styles") {
    filtroPesquisarStyles();
  } else if (acao == "unidades") {
    filtroPesquisarUnidades();
  } else if (acao == "condicoes") {
    filtroPesquisarCondicoes();
  } else if (acao == "clientes") {
    filtroPesquisarClientes();
  } else if (acao == "tabelavendas") {
    filtroPesquisarTabelaVendas();
  } else if (acao == "carteirascontratos") {
    filtroPesquisarCarteirasContratos(par1, par2);
  } else if (acao == "financeirocontas") {
    filtroPesquisarFinanceiroContas();
  } else if (acao == "financeirocontasparcelas") {
    filtroPesquisarFinanceiroContasParcelas();
  }
}

//FINANCEIRO CONTAS A PAGAR
function filtroPesquisarFinanceiroContas() {
  preLoadingOpen();

  $.post(
    $.trim($("#hddFinanceiroFiltrarContasPagar").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          consultarFinanceiroContas();
          preLoadingClose();
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
        preLoadingOpen();
      }
    },
    "json"
  );
}

function enterPesquisarFinanceiroContas(e) {
  if (e.keyCode == 13) {
    consultarFinanceiroContas();
  }
}

function consultarFinanceiroContas() {
  $("#divPesquisarFinanceiroContas").html(strCarregando);

  $.post(
    $.trim($("#hddFinanceiroConsultarContasPagar").val()),
    {
      CPG_Pesquisar: $.trim($("#CPG_Pesquisar2").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divPesquisarFinanceiroContas").html(data.strHtml);

        if (data.totalRegistros > 0) {
          requireDataTables(false);
        }
      } else {
        $("#divPesquisarFinanceiroContas").html("");
        $.notify(data.mensagem, "warn");
      }
    },
    "json"
  );
}

function selecionarFinanceiroContas(pagarID, pagarNumero, strFornecedor) {
  $("#CPG_ID").val(pagarID);
  $("#CPG_Numero").val(pagarNumero);
  $("#CPG_Pesquisar").val(strFornecedor);
  $("#CPG_Pesquisar2, #txtDataInicial, #txtDataFinal").val("");
  $("#divPesquisarFinanceiroContas").html("");
  $("#btnSalvar").trigger("click");
  $(".modal").modal("hide");

  if ($("#ADI_ID") != undefined) {
  } else {
    consultarFinanceiroParcelasPorContasPagar();
  }
}

//CARTEIRAS CONTRATOS
function filtroPesquisarCarteirasContratos(aprovado, distrato) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddCarteirasContratosFiltrarPesquisa").val()),
    {
      ECAR_Aprovado: $.trim(aprovado),
      ECAR_Distrato: $.trim(distrato),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          consultarCarteirasContratos();
          preLoadingClose();
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
        preLoadingOpen();
      }
    },
    "json"
  );
}

function enterPesquisarCarteirasContratos(e) {
  if (e.keyCode == 13) {
    consultarCarteirasContratos();
  }
}

function consultarCarteirasContratos() {
  $("#divPesquisarCarteirasContratos").html(strCarregando);

  $.post(
    $.trim($("#hddCarteirasContratosConsultarPesquisa").val()),
    {
      ECAR_Aprovado: $.trim($("#ECAR_Aprovado").val()),
      ECAR_Distrato: $.trim($("#ECAR_Distrato").val()),
      CTO_Pesquisar: $.trim($("#CTO_Pesquisar2").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divPesquisarCarteirasContratos").html(data.strHtml);

        if (data.totalRegistros > 0) {
          requireDataTables(false);
        }
      } else {
        $.notify(data.mensagem, "warn");
      }
    },
    "json"
  );
}

function selecionarCarteirasContratos(contratoID, contratoNumero, strCliente) {
  $("#CTO_ID").val(contratoID);
  $("#CTO_Numero").val(contratoNumero);
  $("#CTO_Pesquisar").val(strCliente);
  $("#CTO_Pesquisar2").val("");
  $("#divPesquisarCarteirasContratos").html("");
  $("#btnCloseDialogAlert").trigger("click");
  $('#btnFiltrar').trigger('click');
  consultarCarteiraContratosParcelasPorContrato();
}

//TABELA VENDAS
function filtroPesquisarTabelaVendas() {
  if ($.trim($("#UNI_ID").val()) == "") {
    $.notify("Unidade precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CON_ID").val()) == "") {
    $.notify("Condição precisa ser informada.", "warn");
    return;
  } else {
    preLoadingOpen();
    $.post(
      $.trim($("#hddTabelaVendasFiltrar").val()),
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          dialogAlert(data.strTitulo, data.strHtml, 3);

          setTimeout(function () {
            consultarTabelaVendas();
            preLoadingClose();
          }, 1000);
        } else {
          $.notify(data.mensagem, "error");
          preLoadingOpen();
        }
      },
      "json"
    );
  }
}

function enterPesquisarTabelaVendas(e) {
  if (e.keyCode == 13) {
    consultarTabelaVendas();
  }
}

function consultarTabelaVendas() {
  $("#divPesquisarTabelaVendas").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddTabelaVendasConsultar").val()),
    dataType: "json",
    cache: false,
    data: {
      CTB_Pesquisar: $.trim($("#CTB_Pesquisar").val()),
      UNI_ID: $.trim($("#UNI_ID").val()),
      CON_ID: $.trim($("#CON_ID").val()),
    },
    type: "POST",
  }).success(function (data) {
      $("#btnAdicionarCorretor").prop("disabled", false);
      $("#btnAdicionarCorretor").html(strLabel);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#divPesquisarTabelaVendas").html(data.strHtml);

      if (data.totalRegistros > 0) {
        requireDataTables(false);
      }

    }).fail(function (data) {
      $("#divPesquisarTabelaVendas").html('');

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function selecionarTabelaVendas(serieID, periodo) {
  $("#CSE_ID").val(serieID);
  $("#CTB_Periodo").val(periodo);
  $("#CLI_Pesquisar").val("");
  $("#divPesquisarTabelaVendas").html("");
  $("#btnCloseDialogAlert").trigger("click");
}

//CLIENTES
function filtroPesquisarClientes() {
  $.post(
    $.trim($("#hddClientesFiltroPesquisa").val()),
    {
      CLI_Codigo: $.trim($("#CLI_Codigo").val()),
      CLI_Pesquisar: $.trim($("#CLI_Pesquisar").val()),
    },
    function (data) {
      // alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(data.strTitulo, data.strHtml, 3);

        if (data.totalRegistros > 0) {
          requireDataTablesDialog();
        }

        setTimeout(function () {
          consultarClientes();
        }, 500);
      } else {
        $.notify(data.mensagem, "error");
      }
      $("#hddExecutar").val("");
    },
    "json"
  );
}

function enterPesquisarClientes(e) {
  if (e.keyCode == 13) {
    consultarClientes();
  }
}

function consultarClientes() {
  $("#divPesquisarClientes").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddClientesFiltroConsultar").val()),
    dataType: "json",
    cache: false,
    data: {
      GRE_ID: $.trim($("#GRE_ID").val()),
      CLI_Pesquisar: $.trim($("#CLI_Pesquisar2").val()),
    },
    type: "POST",
  }).success(function (data){
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#divPesquisarClientes").html(data.strHtml);
      $("#modalBootstrapDialog_title").html(data.strTitulo);

      if (data.totalRegistros > 0) {
        requireDataTablesDialog();
      }

    }).fail(function (data){
      $("#divPesquisarClientes").html('');
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function selecionarCliente(id, codigo, nome) {
  $("#CLI_ID, #ENT_ID").val(id);
  $("#CLI_ID, #ENT_ID").append(
    "<option selected value='" + id + "'>" + codigo + " - " + nome + "</option>"
  );
  $("#ENT_ID").trigger("chosen:updated");

  $("#CLI_Codigo, #ENT_Codigo").val(codigo);
  $("#CLI_Pesquisar, #ENT_Pesquisar").val(nome);
  $("#CLI_Pesquisar2, #ENT_Pesquisar2").val("");
  $("#divPesquisarClientes").html("");
  $("#btnCloseDialogAlert").trigger("click");
}

////CONDIÇÕES
function filtroPesquisarCondicoes() {
  $.post(
    $.trim($("#hddCondicoesFiltroPesquisar").val()),
    {
      CON_Numero: $.trim($("#CON_Numero").val()),
      CON_Pesquisar: $.trim($("#CON_Pesquisar").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#filtro-titulo").html(data.strTitulo);
        $("#filtro-conteudo").html(data.strHtml);

        consultarCondicoes();
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function enterPesquisarCondicoes(e) {
  if (e.keyCode == 13) {
    consultarCondicoes();
  }
}

function consultarCondicoes() {
  $("#divPesquisarCondicoes").html(strCarregando);

  $.post(
    $.trim($("#hddCondicoesFiltroConsultar").val()),
    {
      CON_Pesquisar: $.trim($("#CON_Pesquisar2").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divPesquisarCondicoes").html(data.strHtml);

        if (data.totalRegistros > 0) {
          requireDataTables(false);
        }
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function selecionarCondicoes(id, codigo, nome) {
  $("#CON_ID").val(id);
  $("#CON_Numero").val(codigo);
  $("#CON_Pesquisar").val(nome);
  $("#divPesquisarCondicoes").html("");
  $("#filtro-titulo").html("");
  $("#filtro-conteudo").html("");
  $("#filtrar-padrao").modal("toggle");
}

//UNIDADES
function filtroPesquisarUnidades() {
  $.post(
    $.trim($("#hddUnidadesFiltroPesquisar").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#filtro-titulo").html(data.strTitulo);
        $("#filtro-conteudo").html(data.strHtml);

        consultarFiltroUnidades();
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function enterPesquisarUnidades(e) {
  if (e.keyCode == 13) {
    consultarFiltroUnidades();
  }
}

function consultarFiltroUnidades() {
  $("#divPesquisarUnidades").html(strCarregando);

  $.post(
    $.trim($("#hddUnidadesFiltroConsultar").val()),
    {
      UNI_Pesquisar: $.trim($("#UNI_Pesquisar2").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divPesquisarUnidades").html(data.strHtml);

        if (data.totalRegistros > 0) {
          requireDataTables(false);
        }
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function selecionarUnidades(id) {
  $("#UNI_ID").val("");
  $("#CON_ID").html("");

  $.post(
    $.trim($("#hddUnidadesFiltroSelecionar").val()),
    {
      UNI_ID: $.trim(id),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#UNI_ID").val(data.UNI_ID);
        $("#UNI_Pesquisar").val(data.UNI_Descricao);
        $("#BLO_ID").val(data.UNI_ID);
        $("#BLO_Descricao").val(data.BLO_Descricao);
        $("#EST_ID").val(data.UNI_ID);
        $("#EST_Descricao").val(data.EST_Descricao);
        $("#UNI_AreaPrivida").val(data.UNI_AreaPrivida);
        $("#UNI_Vagas").val(data.UNI_Vagas);

        if ($.trim($("#PRO_DataProposta").val()) != "") {
          carregarCondicoesUnidades(id);
        }
      } else {
        $.notify(data.mensagem, "error");
      }

      $("#filtrar-padrao").modal("toggle");
    },
    "json"
  );
}

//STYLES
function filtroPesquisarStyles() {
  $.post(
    $.trim($("#hddStylesFiltro").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          consultarFiltroStyles();
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function enterPesquisarStyles(e) {
  if (e.keyCode == 13) {
    consultarFiltroStyles();
  }
}

function consultarFiltroStyles() {
  $("#divPesquisarStyles").html(strCarregando);

  $.post(
    $.trim($("#hddStylesConsultar").val()),
    {
      SIM_Pesquisar: $.trim($("#SIM_Pesquisar").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divPesquisarStyles").html(data.strHtml);

        if (data.totalRegistros > 0) {
          requireDataTables(false);
        }
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function selecionarStyles(nome) {
  $("#ROT_IconeClass").val("");

  $.post(
    $.trim($("#hddStylesSelecionar").val()),
    {
      SIM_Nome: $.trim(nome),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#htmlIconeSelecionado").addClass(nome);
        $("#ROT_IconeClass").val(nome);
      } else {
        $.notify(data.mensagem, "error");
      }
      $("#btnCloseDialogAlert").trigger("click");
    },
    "json"
  );
}

//ITENS DOCUMENTOS - MEDIÇÕES
function filtroPesquisarItensDocumentosMedicoes() {
  $(".btn-formulario, .btn-filtro").prop("disabled", true);

  $.ajax({
    url: $.trim($("#hddDocumentosFiltrarMedicoes").val()),
    dataType: "json",
    cache: false,
    data: {
      SGP_Data: true,
    },
    type: "POST",
  }).success(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#filtro-titulo").html(strCarregando);
      $("#filtro-conteudo").html(data.strHtml);

      consultarItensDocumentosFiltroMedicoes();
    }).fail(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarItensDocumentosMedicoes(e) {
  if (e.keyCode == 13) {
    consultarItensDocumentosFiltroMedicoes();
  }
}

function consultarItensDocumentosFiltroMedicoes() {
  $("#divPesquisarItensDocumentos").html(strCarregando);
  $(".btn-formulario, .btn-filtro").prop("disabled", true);

  $.ajax({
    url: $.trim($("#hddDocumentosConsultarFiltroMediacoes").val()),
    dataType: "json",
    cache: false,
    data: {
      DOC_ID: $.trim($("#DOC_ID").val()),
      EMP_ID: $.trim($("#EMP_ID").val()),
      ENT_ID: $.trim($("#ENT_ID").val()),
      DOI_Pesquisar: $.trim($("#DOI_Pesquisar").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);

      if (data.error) {
        $("#divPesquisarItensDocumentos").html("");
        $("#filtro-titulo").html("");

        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#divPesquisarItensDocumentos").html(data.strHtml);
      $("#filtro-titulo").html(data.strTitulo);

      if (data.totalRegistros > 0) {
        requireDataTables(false, true, true, true, true, false, false);
      }
    })
    .fail(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);
      $("#divPesquisarItensDocumentos").html("");
      $("#filtro-titulo").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function selecionarDocumentosItemMedicao(id) {
  $("#lbl-item-documento-medicao").html(strCarregando);
  $("#MEI_ID").val("");

  var strLabelSelecionado = $.trim(
    $("#hddLabelDocumentosItemMedicaoSelecionado").val()
  );

  $.post($.trim($("#hddDocumentosSelecionarMedicao").val()),{
      DOC_ID: $.trim($("#DOC_ID").val()),
      ENT_ID: $.trim($("#ENT_ID").val()),
      MEI_ID: $.trim(id),
    },
    function (data) {
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          $("#MEI_ID").val(id);
          $("#btnFiltrarItensDocumentosMedicoes").hide();
          $("#divFiltroDocumentoItensPedidos").hide();
          $("#DOI_Quantidade").val(data.douQuantidade);
          $("#DOI_Percentual_IPI").val(data.douPercentualIPI);
          $("#DOI_Percentual_IIS").val(data.douPercentualIIS);
          $("#DOI_Percentual_ICMS").val(data.douPercentualICMS);
          $("#DOI_ValorUnitario").val(data.douValorUnitario);
          $("#DOI_ValorTotal").val(data.douValorTotal);
          $("#DOI_ValorFrete2").val(data.douValorFrete);
          $("#DOI_PercentualCaucao").val("");
          $("#divPercentualCaucao").hide();

          if (data.strRetemCaucao == strSim) {
            $("#DOI_PercentualCaucao").val(data.douPercentualCaucao);
            $("#divPercentualCaucao").show();
          }

          $("#lbl-item-documento-medicao").html(
            strLabelSelecionado +
            data.arrDados[0].INS_Codigo +
            " - " +
            data.arrDados[0].INS_Descricao +
            " (" +
            data.arrDados[0].UNM_Descricao +
            ")"
          );
        } else {
          $.notify(data.mensagem, "error");
        }
      } else {
        $.notify(data.mensagem, "error");
        $("#lbl-item-documento-medicao").html("");
      }

      $("#filtrar-padrao").modal("toggle");
    },
    "json"
  );
}

//ITENS DOCUMENTOS - PEDIDOS
function filtroPesquisarItensDocumentosPedidos() {
  $.post(
    $.trim($("#hddDocumentosFiltrar").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#filtro-titulo").html(strCarregando);
        $("#filtro-conteudo").html(data.strHtml);

        consultarItensDocumentosFiltroPedidos();

        setTimeout(function () {
          $('#DOI_Pesquisar').focus();
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function enterPesquisarItensDocumentosPedidos(e) {
  if (e.keyCode == 13) {
    consultarItensDocumentosFiltroPedidos();
  }
}

function consultarItensDocumentosFiltroPedidos() {
  $("#divPesquisarItensDocumentos").empty();
  $("#divPesquisarItensDocumentos").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddDocumentosConsultarFiltro").val()),
    dataType: "json",
    cache: false,
    data: {
      DOC_ID: $.trim($("#DOC_ID").val()),
      EMP_ID: $.trim($("#EMP_ID").val()),
      ENT_ID: $.trim($("#ENT_ID").val()),
      DOI_Pesquisar: $.trim($("#DOI_Pesquisar").val()),
    },
    type: "POST",
  }).success(function (data) {
      if (data.error){
        $("#divPesquisarItensDocumentos").html('');
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#divPesquisarItensDocumentos").html(data.strHtml);
      $("#filtro-titulo").html(data.strTitulo);

      if (data.totalRegistros > 0) {
        requireDataTables(false, true, true, true, true, false, false);
      }

    }).fail(function (data){
      $("#divPesquisarItensDocumentos").html('');
      dialogAlert(strAtencao, data.responseText, 6);
    });

  /*$.post(
    $.trim($("#hddDocumentosConsultarFiltro").val()),
    {
      DOC_ID: $.trim($("#DOC_ID").val()),
      EMP_ID: $.trim($("#EMP_ID").val()),
      ENT_ID: $.trim($("#ENT_ID").val()),
      DOI_Pesquisar: $.trim($("#DOI_Pesquisar").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divPesquisarItensDocumentos").html(data.strHtml);
        $("#filtro-titulo").html(data.strTitulo);
      } else {
        // $.notify(data.mensagem, "error");
      }
    },
    "json"
  );*/
}

function selecionarDocumentosItemPedido(id) {
  $("#lbl-item-documento-pedido").html(strCarregando);
  $("#PDI_ID").val("");

  var strLabelSelecionado = $.trim(
    $("#hddLabelDocumentosItemPedidoSelecionado").val()
  );

  $.post(
    $.trim($("#hddDocumentosSelecionarPedido").val()),
    {
      DOC_ID: $.trim($("#DOC_ID").val()),
      ENT_ID: $.trim($("#ENT_ID").val()),
      PDI_ID: $.trim(id),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          $("#PDI_ID").val(id);
          $("#btnFiltrarItensDocumentosPedidos").hide();
          $("#divFiltroDocumentoItensMedicoes").hide();
          $("#DOI_Quantidade").val(data.douQuantidade);
          $("#DOI_Percentual_IPI").val(data.douPercentualIPI);
          $("#DOI_Percentual_IIS").val(data.douPercentualIIS);
          $("#DOI_Percentual_ICMS").val(data.douPercentualICMS);
          $("#DOI_ValorUnitario").val(data.douValorUnitario);
          $("#DOI_ValorTotal").val(data.douValorTotal);
          $("#lbl-item-documento-pedido").html(
            strLabelSelecionado +
            data.arrDados[0].INS_Codigo +
            " - " +
            data.arrDados[0].INS_Descricao +
            " (" +
            data.arrDados[0].UNM_Descricao +
            ")"
          );
          calcularItemDocumento();

          $("#DOI_ValorFrete2").val(data.douValorFrete);
          $("#DOI_ValorDesconto2").val(data.douValorDesc);
        } else {
          $.notify(data.mensagem, "error");
        }
      } else {
        $.notify(data.mensagem, "error");
        $("#lbl-item-documento-pedido").html("");
      }
      $("#filtrar-padrao").modal("toggle");
    },
    "json"
  );
}

//ITENS MEDIÇÕES
function filtroPesquisarItensMedicoes() {
  $.post(
    $.trim($("#hddContratosFiltrar").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#filtro-titulo").html(data.strTitulo);
        $("#filtro-conteudo").html(data.strHtml);

        consultarItensMedicoesFiltro();
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function enterPesquisarItensMedicoes(e) {
  if (e.keyCode == 13) {
    consultarItensMedicoesFiltro();
  }
}

function consultarItensMedicoesFiltro() {
  $("#divPesquisarItensMedicoes").html(strCarregando);

  $.post(
    $.trim($("#hddContratosConsultarFiltro").val()),
    {
      CON_ID: $.trim($("#CON_ID").val()),
      ICT_Pesquisar: $.trim($("#ICT_Pesquisar").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divPesquisarItensMedicoes").html(data.strHtml);

        requireDataTables(false);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function selecionarItemMedicao(id, nome, valor, saldo) {
  $("#divFiltroItensMedicoes").removeClass("has-error").addClass("has-success");
  $("#lbl-filtro-itens-medicoes").html(strLabelItemSelecionado + " " + nome);
  $("#MEI_IDFiltro").val(id);
  $("#MEI_Pesquisar").val("");
  $("#divPesquisarItensMedicoes").html("");
  $("#filtro-titulo").html("");
  $("#filtro-conteudo").html("");

  $("#DOI_ValorUnitario").val(valor);
  if (textoParaFloat(saldo) > 0) {
    $("#divSaldoItemMedicao").removeClass("has-error").addClass("has-success");
  } else {
    $("#divSaldoItemMedicao").removeClass("has-success").addClass("has-error");
  }

  $("#MEI_QuantidadeSaldo").val(saldo);
  $("#filtrar-padrao").modal("toggle");
}

//ITENS CONTRATOS
function filtroPesquisarItensContratos() {
  $.post(
    $.trim($("#hddContratosFiltrar").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#filtro-titulo").html(data.strTitulo);
        $("#filtro-conteudo").html(data.strHtml);

        consultarItensContratosFiltro();
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function enterPesquisarItensContratos(e) {
  if (e.keyCode == 13) {
    consultarItensContratosFiltro();
  }
}

function consultarItensContratosFiltro() {
  $("#divPesquisarItensContratos").html(strCarregando);

  $.post(
    $.trim($("#hddContratosConsultarFiltro").val()),
    {
      CON_ID: $.trim($("#CON_ID").val()),
      ICT_Pesquisar: $.trim($("#ICT_Pesquisar").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divPesquisarItensContratos").html(data.strHtml);
        requireDataTables(false, true, true, true, true, false, false);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function selecionarItemContrato(id, nome, valor, saldo, total) {
  $("#divFiltroItensContratos")
    .removeClass("has-error")
    .addClass("has-success");
  $("#lbl-filtro-itens_contratos").html(strLabelItemSelecionado + " " + nome);
  $("#ICT_IDFiltro").val(id);
  $("#ICT_Pesquisar").val("");
  $("#divPesquisarItensContratos").html("");
  $("#filtro-titulo").html("");
  $("#filtro-conteudo").html("");

  $("#MEI_ValorUnitario").val(valor);
  //floatParaTexto(
  if (textoParaFloat(saldo) > 0) {
    $("#divSaldoItemContrato").removeClass("has-error").addClass("has-success");
  } else {
    $("#divSaldoItemContrato").removeClass("has-success").addClass("has-error");
  }

  if (
    $("#MEI_Quantidade").val() != undefined &&
    $.trim($("#lbl-filtro-itens_contratos").html()) != ""
  ) {
    $("#MEI_Quantidade").trigger("blur");
  }

  $("#ICT_QuantidadeSaldo").val(saldo);
  $("#MEI_ValorSaldo").val(total);
  $("#filtrar-padrao").modal("toggle");
}

////EMPRESAS
function filtroPesquisarEmpresas() {
  $(".btn-formulario").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#empresas_filtrar_rapido").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_Codigo: $.trim($("#EMP_Codigo").val()),
      EMP_Pesquisar: $.trim($("#EMP_Pesquisar").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      dialogAlert(data.strTitulo, data.strHtml, 3);

      setTimeout(function () {
        consultarEmpresas();
      }, 1000);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarEmpresas2(e) {
  if (e.keyCode == 13) {
    consultarEmpresas2();
  }
}

function enterPesquisarEmpresas(e) {
  if (e.keyCode == 13) {
    consultarEmpresas();
  }
}

function consultarEmpresas() {
  $(".btn-formulario").prop("disabled", true);
  $("#divPesquisarEmpresas").html(strCarregando);

  $.ajax({
    url: $.trim($("#empresas_filtrar_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      SGP_Pesquisar: $.trim($("#SGP_PesquisarEmpresa").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        $("#divPesquisarEmpresas").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#divPesquisarEmpresas").html(data.strHtml);

      if (data.totalRegistros > 0) {
        requireDataTables(false, true, true, true, true, false, false);
      }
    })
    .fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function selecionarEmpresa(id, codigo, nome) {
  $("#EMP_ID").val(id);
  $("#EMP_Codigo").val(codigo);
  $("#EMP_Pesquisar").val(nome);
  $("#divPesquisarEmpresas, #filtro-titulo, #filtro-conteudo").html("");
  $(".modal").modal("hide");
  $("#EMP_Codigo").trigger("blur").trigger("change");
}

////EMPRESAS 2
function filtroPesquisarEmpresas2() {
  $(".btn-formulario").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#empresas_filtrar_rapido").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_Codigo: $.trim($("#EMP_Codigo2").val()),
      EMP_Pesquisar: $.trim($("#EMP_Pesquisar3").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      dialogAlert(data.strTitulo, data.strHtml, 3);

      setTimeout(function () {
        consultarEmpresas2();
      }, 1000);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarEmpresas2() {
  $("#divPesquisarEmpresas").html(strCarregando);
  $(".btn-formulario").prop("disabled", true);

  $.ajax({
    url: $.trim($("#empresas_filtrar_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_Codigo: $.trim($("#EMP_Codigo2").val()),
      EMP_Pesquisar: $.trim($("#SGP_PesquisarEmpresa").val()),
      EMP_ID2: true,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        $("#divPesquisarEmpresas").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#divPesquisarEmpresas").html(data.strHtml);

      if (data.totalRegistros > 0) {
        requireDataTables(false, true, true, true, true, false, false);
      }
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#divPesquisarEmpresas").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function selecionarEmpresa2(id, codigo, nome) {
  $("#EMP_ID2").val(id);
  $("#EMP_Codigo2").val(codigo);
  $("#EMP_Pesquisar3").val(nome);
  $("#EMP_Codigo2").trigger("blur");
  $(".modal").modal("hide");
}

//FORNECEDORES
function filtroPesquisarFornecedores() {
  $.post(
    $.trim($("#hddFornecedoresFiltrar").val()),
    {
      ENT_Codigo: $.trim($("#ENT_Codigo").val()),
      ENT_Pesquisar: $.trim($("#ENT_Pesquisar").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#filtro-conteudo").html(data.strHtml);

        consultarFornecedores();
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function enterPesquisarFornecedores(e) {
  if (e.keyCode == 13) {
    consultarFornecedores();
  }
}

function consultarFornecedores() {
  $("#divPesquisarFornecedores").html(strCarregando);

  $.post(
    $.trim($("#hddFornecedoresConsultar").val()),
    {
      ENT_Pesquisar: $.trim($("#ENT_Pesquisar2").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#filtro-titulo").html(data.strTitulo);
        $("#divPesquisarFornecedores").html(data.strHtml);

        if (data.totalRegistros > 0) {
          requireDataTables(false, true, true, true, true, false, false, false);
        }
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function selecionarFornecedor(id, codigo, nome) {
  $("#ENT_ID").val(id);
  $("#ENT_Codigo").val(codigo);
  $("#ENT_Pesquisar").val(nome);
  $("#ENT_Pesquisar2").val("");
  /* 	$('#divPesquisarFornecedores').html('');
  $('#filtro-titulo').html('');
  $('#filtro-conteudo').html(''); */
  $(".modal").modal("hide");
}

//BENEFICIARIOS
function filtroPesquisarBeneficiarios() {
  $.post(
    $.trim($("#entidades_beneficiarios_filtrar").val()),
    {
      ENT_Codigo: $.trim($("#ENT_CodigoBeneficiario").val()),
      ENT_Pesquisar: $.trim($("#ENT_PesquisarBeneficiario").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        //dialogAlert2(data.strTipo, data.strHtml, 3);
        $("#filtro-conteudo").html(data.strHtml);

        consultarBeneficiarios();
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function enterPesquisarBeneficiarios(e) {
  if (e.keyCode == 13) {
    consultarBeneficiarios();
  }
}

function consultarBeneficiarios() {
  $("#divPesquisarFornecedores2").html(strCarregando);

  $.post(
    $.trim($("#entidades_beneficiarios_filtrar_consultar").val()),
    {
      ENT_Pesquisar: $.trim($("#ENT_Pesquisar3").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#filtro-titulo").html(data.strTitulo);
        $("#divPesquisarFornecedores2").html(data.strHtml);

        if (data.totalRegistros > 0) {
          requireDataTables(false, true, true, true, true, false, false, false);
        }
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function selecionarBeneficiarios(id, codigo, nome) {
  $("#ENT_ID2").val(id);
  $("#ENT_CodigoBeneficiario").val(codigo);
  $("#ENT_PesquisarBeneficiario").val(nome);
  /* 	$('#divPesquisarFornecedores2').html('');
  $('#filtro-titulo').html('');
  $('#filtro-conteudo').html(''); */
  $(".modal").modal("hide");
}

///PROJETOS
function filtroPesquisarProjetos() {
  $.post(
    $.trim($("#hddProjetosFiltrar").val()),
    {
      PRO_Codigo: $.trim($("#PRO_Codigo").val()),
      PRO_Pesquisar: $.trim($("#PRO_Pesquisar").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#filtro-titulo").html(data.strTitulo);
        $("#filtro-conteudo").html(data.strHtml);

        consultarProjetos();
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function enterPesquisarProjetos(e) {
  if (e.keyCode == 13) {
    consultarProjetos();
  }
}

function consultarProjetos() {
  preLoadingOpen();
  $("#divPesquisarProjetos").html(strCarregando);

  $.post(
    $.trim($("#hddProjetosConsultar").val()),
    {
      PRO_Pesquisar: $.trim($("#PRO_Pesquisar2").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divPesquisarProjetos").html(data.strHtml);

        if (data.totalRegistros > 0) {
          requireDataTables(false);
        }
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingOpen();
      return;
    },
    "json"
  );
}

function selecionarProjeto(id, codigo, nome) {
  $("#PRO_ID").val(id);
  $("#PRO_Codigo").val(codigo);
  $("#PRO_Pesquisar").val(nome);
  $("#divPesquisarProjetos").html("");
  $("#filtro-titulo").html("");
  $("#filtro-conteudo").html("");
  $(".modal").modal("hide");
}

//INSUMOS
function filtroPesquisarInsumos() {
  $(".btn-formulario, .btn-filtro").prop("disabled", true);

  //hddInsumosDados
  $.ajax({
    url: $.trim($("#insumos_filtrar_dados").val()),
    dataType: "json",
    cache: false,
    data: {
      SGP_Valor: true,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3, "dialogInsumosPesquisar");

      setTimeout(function () {
        $("#SGP_Pesquisar").focus();
      }, 1000);
    })
    .fail(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarPesquisarInsumos() {
  $(".btn-formulario, .btn-filtro").prop("disabled", true);
  $("#consultar-dados-dialog, #spnTotalRegistrosDialog").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddInsumosDados").val()),
    dataType: "json",
    cache: false,
    data: {
      INS_Codigo: $.trim($("#INS_Codigo").val()),
      SGP_Pesquisar: $.trim($("#SGP_Pesquisar").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);

      if (data.error) {
        $("#consultar-dados-dialog, #spnTotalRegistrosDialog").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#spnTotalRegistrosDialog").html("(" + data.totalRegistros + ")");
      $("#consultar-dados-dialog").html(data.strHtml);

      $("#paginationDialog").html(data.pagination);
      $("#paginationDialog").on("click", "a", function (e) {
        e.preventDefault();
        var pageno = $(this).attr("data-ci-pagination-page");
        loadPagination(
          data.url,
          pageno,
          data.arrFiltros,
          "paginationDialog",
          "consultar-dados-dialog"
        );
      });
    })
    .fail(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);
      $("#consultar-dados-dialog, #spnTotalRegistrosDialog").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarInsumos(e) {
  if (e.keyCode == 13) {
    consultarInsumos();
  }
}

function enterPesquisarInsumosFiltro(e) {
  if (e.keyCode == 13) {
    consultarPesquisarInsumos();
  }
}

function consultarInsumos() {
  $(document).ready(function () {
    $("#divPesquisarInsumos").html(strCarregando);

    $.post(
      $.trim($("#hddInsumosConsultar").val()),
      {
        INS_Pesquisar: $.trim($("#INS_Pesquisar2").val()),
        strTipo: $.trim($("#hddExecutar").val()),
      },

      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#divPesquisarInsumos").html(data.strHtml);
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  });
}

function selecionarInsumo(id, codigo, nome) {
  $("#hddExecutar").val("");
  $("#INS_ID").val(id);
  $("#INS_Codigo").val(codigo);
  $("#INS_Pesquisar").val(nome);
  $("#dialogInsumosPesquisar").modal("hide");
}

//FORNECEDORES COTAÇÕES
function filtroPesquisarFornecedoresCotacoes() {
  $.ajax({
    url: $.trim($("#hddFornecedoresCotacoesFiltrar").val()),
    dataType: "json",
    cache: false,
    data: {
      SGP_Valor: true
    },
    type: "POST",
  }).success(function (data){

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#filtro-titulo").html(data.strTitulo);
      $("#filtro-conteudo").html(data.strHtml);
      consultarFornecedoresCotacoes();

    }).fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarFornecedoresCotacoes(e) {
  if (e.keyCode == 13) {
    consultarFornecedoresCotacoes();
  }
}

function consultarFornecedoresCotacoes() {
  $("#divPesquisarFornecedores").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddFornecedoresCotacoesConsultar").val()),
    dataType: "json",
    cache: false,
    data: {
      ENT_Pesquisar: $.trim($("#ENT_Pesquisar").val()),
    },
    type: "POST",
  }).success(function (data){
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#filtro-titulo").html(data.strTitulo);
      $("#divPesquisarFornecedores").html(data.strHtml);

      if (data.totalRegistros > 0) {
        requireDataTables(false, false, false, false, false, false);
      }

    }).fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function selecionarFornecedoresCotacoes(fornecedorID) {

  $.ajax({
    url: $.trim($("#hddSalvarItensCotacoesFornecedores").val()),
    dataType: "json",
    cache: false,
    data: {
      ENT_ID: $.trim(fornecedorID),
      COT_ID: $.trim($("#COT_ID").val()),
    },
    type: "POST",
  }).success(function (data) {
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#filtrar-padrao").modal("hide");
      consultarItensCotacoes($.trim($("#COT_ID").val()));

    }).fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });  
}

function consultarOrcamentos() {
  $(".btn-filtro").prop("disabled", true);
  var strLabel = $("#btnFiltrar").html();
  $("#btnFiltrar, #consultar-dados").html(strCarregando);
  $("#spnTotalRegistrosConsultar").show();
  $("#spnTotalRegistrosConsultar").html(strCarregandoIcone);
  preLoadingOpen();

  var arrEmpresas = new Array();
  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#orcamentos_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: arrEmpresas,
      ORC_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      ORC_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
      ORC_Pesquisar: $.trim($("#ORC_Pesquisar").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#btnFiltrar").html(strLabel);
      preLoadingClose();

      if (data.error) {
        $("#spnTotalRegistrosConsultar, #consultar-dados").html("");
        $("#spnTotalRegistrosConsultar").hide();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#consultar-dados").html(data.strHtml);
      $("#pagination").html(data.pagination);
      $("#pagination").on("click", "a", function (e) {
        e.preventDefault();
        var pageno = $(this).attr("data-ci-pagination-page");
        loadPagination(data.url, pageno, data.arrFiltros);
      });

      $("#spnTotalRegistrosConsultar").html(data.totalRegistros);
    })
    .fail(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#btnFiltrar").html(strLabel);
      $("#spnTotalRegistrosConsultar, #consultar-dados").html("");
      $("#spnTotalRegistrosConsultar").hide();
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarOrcamentos(e) {
  if (e.keyCode == 13) {
    $("#txtDataInicial, #txtDataFinal").val("");
    consultarOrcamentos();
  }
}

function consultarItensOrcamentos(orcamentoID, atualizar = '') {
  $(".btn-formulario").prop("disabled", true);
  var strLabel = $("#itens-orcamentos").html();
  $("#itens-orcamentos").html(strCarregando);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#hddConsultarItensOrcamentos").val()),
    dataType: "json",
    cache: false,
    data: {
      ORC_ID: $.trim(orcamentoID),
      EMP_ID: $.trim($("#EMP_ID").val()),
      SGP_Atualizar: $.trim(atualizar)
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        $("#itens-orcamentos").html(strLabel);
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#itens-orcamentos").html(data.strHtml);
      $("#spnTotalRegistros").html(data.totalRegistros);
      $(".collapse").on("show.bs.collapse", function () {
        $(".collapse.in").collapse("hide");
      });

      for (i in data.arrCodigos) {
        $(".linhaTabela" + data.arrCodigos[i]).on("click", function (event) {
          event.preventDefault();
          var nodeName = event.target.nodeName;

          if (nodeName == "BUTTON") {
            $("#demo" + this.id).show();
            if ($.trim($("#demo" + this.id).html()) == "") {
              planejamentoOrcamentoItensPorCodigo(this.id, $(this).attr("alt"));
            } else {
              $("#demo" + this.id).html("");
              $("#demo" + this.id).hide();
            }
          }
          event.stopPropagation();
        });

        i++;
      }
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#itens-orcamentos").html(strLabel);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function carregarSelectChosenOrcamentos() {
  $(".btn-expandir, .textDescricao, .selectCampo").prop("disabled", true);
  var strLabel = $(".btn-expandir").html();
  $(".btn-expandir").html(strCarregandoIcone);
  preLoadingOpen();

  setTimeout(function () {
    $("select[name='PLF_Conta[]']").chosen("destroy");
    $("select[name='PLF_Conta[]']").prop("selectedindex", -1);
    $(".chosen-select").chosen({
      case_sensitive_search: false,
      allow_single_deselect: true,
      disable_search_threshold: 5,
      width: "100%",
    });
    $("select[name='PLF_Conta[]']").chosen();
    $("input, select").css({ "font-size": "10px" });
    $(".btn-expandir").html(strLabel);
    $(".btn-expandir, .textDescricao, .selectCampo").prop("disabled", false);
    $(".selectCampo").trigger("chosen:updated");
    preLoadingClose();
  }, 200);
}

function atualizarItensOrcamentos(
  itensOrcamentoID,
  campo,
  valor,
  valortotalID
) {
  $.post(
    $.trim($("#hddAtualizarItensOrcamentos").val()) + "/" + itensOrcamentoID,
    {
      ORC_ID: $.trim($("#ORC_ID").val()),
      OCI_ID: $.trim(itensOrcamentoID),
      strCampo: campo,
      strValor: valor,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if ($.trim(valortotalID) != "" || campo == "PLF_Conta") {
          //consultarItensOrcamentos($.trim($('#ORC_ID').val()));
        }
        //$.notify(data.mensagem, "success");
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function atualizarItensOrcamentosValores(
  itensOrcamentoID,
  quantidadeID,
  valorID,
  totalItemID,
  codigoPai
) {
  $("#tdValorTotal, #" + totalItemID).html(strCarregandoIcone);

  $.ajax({
    url: $.trim($("#hddItensOrcamentosCalcular").val()),
    dataType: "json",
    cache: false,
    data: {
      ORC_ID: $.trim($("#ORC_ID").val()),
      OCI_ID: $.trim(itensOrcamentoID),
      OCI_Quantidade: $("#" + quantidadeID).val(),
      OCI_ValorUnitario: $("#" + valorID).val(),
      OCI_IDPai: codigoPai,
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#" + totalItemID).html(data.douValorTotal);
      $("#tdValorTotal").html(data.douValorGeral);

      if (data.arrCamposEtapas.length > 0) {
        for (var i = 0; i < data.arrCamposEtapas.length; i++) {
          $("#" + data.arrCamposEtapas[i]).html(data.arrValoresEtapas[i]);
        }
      }
    })
    .fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarSolicitacoes() {
  var strLabel = consultarPadraoInicial();
  var arrEmpresas = new Array();
  var arrInsumos = new Array();
  var arrAprovacao = new Array();
  var arrStatus = new Array();
  var arrObras = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='INS_ID[]'] option:selected").each(function () {
    arrInsumos.push($(this).val());
  });

  $("select[name='SEL_SimNao[]'] option:selected").each(function () {
    arrAprovacao.push($(this).val());
  });

  
  $("select[name='SOL_Status[]'] option:selected").each(function () {
    arrStatus.push("'" + $(this).val() + "'");
  });

  $("select[name='CAX_Obra_ID[]'] option:selected").each(function () {
    arrObras.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#solicitacoes_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: arrEmpresas,
      INS_ID: arrInsumos,
      SGP_Numero: $.trim($("#SGP_Numero").val()),
      SOL_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      SOL_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
      SOL_Aprovado: arrAprovacao,
      SOL_Status: arrStatus,
      SOL_Pesquisar: $.trim($("#SGP_Pesquisar").val()),
      CAX_Obra_ID: arrObras,
    },
    type: "POST",
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);
      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarSolicitacoes(e) {
  if (e.keyCode == 13) {
    if ($.trim($("#SGP_Numero").val()) != "") {
      $("#txtDataInicial, #txtDataFinal").val("");
    }

    consultarSolicitacoes();
  }
}

function calcularPercentualItensApropriacoes(){
  $("#btnSalvarItemApropriacao").prop("disabled", true);
  var strLabel = $("#btnSalvarItemApropriacao").html();
  $("#btnSalvarItemApropriacao, #tdTotalPercentual").html(strCarregando);

  // var arrValores = new Array();
  // $("input[name='SOA_Percentual2[]']").each(function () {
  //   arrValores.push($(this).val());
  // });  

  $.ajax({
      url: $.trim($("#hddApropriacoesCalcular").val()),
      dataType: "json",
      cache: false,
      data: {
        SOL_ID: $.trim($("#SOL_ID").val()),
        SIT_ID: $.trim($("#hdditemSolicitacaoID").val()),
        SOA_Percentual: $.trim($("#SOA_Percentual2").val()),
        // arrValores: arrValores,
      },
      type: "POST",
    }).success(function (data){
        $("#btnSalvarItemApropriacao").html(strLabel);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        if (data.sucesso == false){
          $("#div-total").removeClass('has-success has-warning').addClass("has-error");
        }else{
          if (parseFloat(data.limite) == parseFloat(data.total_sem_mascara)){
            $("#div-total").removeClass('has-warning has-error').addClass("has-success");
            $("#btnSalvarItemApropriacao").prop("disabled", false);
          }else{
            $("#div-total").removeClass('has-success has-warning').addClass("has-warning");
            $("#btnSalvarItemApropriacao").prop("disabled", false);
          }
        }

        $("#SOA_PercentualTotal").val(data.total);

      }).fail(function (data){
        $("#btnSalvarItemApropriacao").prop("disabled", false);
        $("#btnSalvarItemApropriacao").html(strLabel);
        $("#div-total").removeClass('has-success has-warning has-warning');
        $("#SOA_PercentualTotal").removeClass('bg-success bg-warning bg-success, bg-error');

        dialogAlert(strAtencao, data.responseText, 6);
      });
}

function calcularPercentualItensApropriacoesAdicionar() {
  $.post(
    $.trim($("#hddApropriacoesCalcular").val()),
    {
      SIT_ID: $.trim($("#hdditemSolicitacaoID").val()),
      SOA_Percentual: $.trim($("#SOA_Percentual2").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#btnSalvarItemApropriacao").prop("disabled", true);

        $("#div-total").removeClass("has-success");
        $("#div-total").removeClass("has-warning");
        $("#div-total").removeClass("has-error");
        $("#label-total").removeClass("has-success");
        $("#label-total").removeClass("has-warning");
        $("#label-total").removeClass("has-error");

        var label = $("#label-total").html();

        if (parseFloat(data.total_sem_mascara) > 100) {

          $("#div-total").addClass("has-error");
          $("#label-total").addClass("has-error");

        }else if (parseFloat(data.total_sem_mascara) == 100.0){

          $("#div-total").addClass("has-success");
          $("#label-total").addClass("has-success");
          $("#btnSalvarItemApropriacao").prop("disabled", false);

        }else{
          $("#btnSalvarItemApropriacao").prop("disabled", false);
          $("#div-total").addClass("has-warning");
          $("#label-total").addClass("has-warning");
        }

        $("#label-total").html(label);
      } else {
        $("#tdTotalPercentual").html("0,00");
      }
    },
    "json"
  );
}

function importarOrcamento(orcamentoID) {

}

function editaItemOrcamento(orcamentoID, codigoSelecionado, strTipoItem) {
  $("#btnSalvarDialog").prop("disabled", true);
  var strLabel = $("#btnSalvarDialog").html();
  $("#btnSalvarDialog").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddEditarItensOrcamentos").val()),
    dataType: "json",
    cache: false,
    data: {
      ORC_ID: $.trim($("#ORC_ID").val()),
      OCI_ID: document.formEdicaoItem.oci_id.value,
      ORC_Pai: codigoSelecionado,
      descricao_item: document.formEdicaoItem.descricao_item.value,
      PLF_Conta: $.trim($("#PLF_Conta").val()),
      UNM_ID: $.trim($("#UNM_ID").val()),
      quantidade_item: $.trim($("#OCI_Quantidade").val()),
      val_unitario_item: $.trim($("#OCI_ValorUnitario").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $("#btnSalvarDialog").prop("disabled", false);
      $("#btnSalvarDialog").html(strLabel);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $(".modal").modal("hide");
      $.notify(data.mensagem, "success");
      consultarItensOrcamentos($.trim($("#ORC_ID").val()));
    })
    .fail(function (data) {
      $("#btnSalvarDialog").prop("disabled", false);
      $("#btnSalvarDialog").html(strLabel);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarItensSolicitacoes(solicitacaoID, arrSelecionados) {
  var strLabel = $("#btnAtualizar").html();
  $("#itens-solicitacoes, #btnAtualizar").html(strCarregando);

  var strVisualizar = $.trim($("#hddNao").val());
  if ($.trim($("#hddVisualizar").val()) != "") {
    strVisualizar = $.trim($("#hddSim").val());
  }

  $.ajax({
    url: $.trim($("#hddConsultarItensSolicitacoes").val()),
    dataType: "json",
    cache: false,
    data: {
      SOL_ID: $.trim(solicitacaoID),
      arrSelecionados: arrSelecionados,
      strVisualizar: strVisualizar,
    },
    type: "POST",
  })
    .success(function (data) {
      $("#btnAtualizar").html(strLabel);
      if (data.error) {
        $("#itens-solicitacoes").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#itens-solicitacoes").html(data.strHtml);
      $("#spnTotalRegistros").html(data.totalRegistros);
      $("#spnTotalRegistrosApropriacoes").html(data.totalRegistrosApropriacoes);

      // calcularPercentualItensApropriacoes();

      if (arrSelecionados != undefined) {
        $(".btnProgramar").show();
      }

      setInitFunctions();
    })
    .fail(function (data) {
      $("#itens-solicitacoes").html("");
      $("#btnAtualizar").html(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarItemApropriacoes(intCodigo) {
  $("#resultadoApropriacoes").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddConsultarApropriacoes").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      SOL_ID: $.trim($("#SOL_ID").val()),
      SIT_ID: $.trim(intCodigo),
    },
  }).success(function (data) {
      if (data.error) {
        $("#resultadoApropriacoes").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#resultadoApropriacoes").html(data.strHtml);
      // $('#spnTotalRegistros').html(data.totalRegistros);
    }).fail(function (data) {
      $("#resultadoApropriacoes").html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function editarItemApropriacao(id) {
  $(".btn-editar").prop("disabled", true);

  $.ajax({
    url: $.trim($("#itens_apropriacoes_editar").val()) + "/" + $.trim(id),
    dataType: "json",
    cache: false,
    data: {
      SOA_ID: id,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-editar").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#SOA_ID").val(data.arrDados[0].SOA_ID);
      $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").prop("disabled", false);
      $("#CEN_ID").val(data.arrDados[0].CEN_ID);
      $("#ORC_ID2").val(data.arrDados[0].ORC_ID);
      $("#PLF_Conta2").val(data.arrDados[0].PLF_Conta);
      $("#SOA_Percentual2").val(data.arrDados[0].SOA_Percentual);
      $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").trigger("chosen:updated");
      $("#ORC_ID2").trigger("change");

      setTimeout(function () {
        $("#OCI_ID2").val(data.arrDados[0].OCI_ID);
        $("#OCI_ID2").trigger("chosen:updated");
      }, 1000);

      $("#SOA_Percentual2").prop("disabled", true);
      $("#CEN_ID").focus();
    })
    .fail(function (data) {
      $(".btn-editar").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function novoItemApropriacao(itemSolicitacaoID, insumoDescricao) {
  $(document).ready(function () {
    preLoadingOpen();

    $.post(
      $.trim($("#hddApropriacoesNovo").val()),
      {
        SOL_ID: $.trim($("#SOL_ID").val()),
        SIT_ID: $.trim(itemSolicitacaoID),
        EMP_ID: $.trim($("#EMP_ID").val()),
        EMP_Info: $.trim($("#EMP_Dados").val()),
        INS_Descricao: $.trim(insumoDescricao),
      },
      function (data) {
        // alert(data); return;
        if (data.sucesso == "true") {
          dialogAlert(data.strTitulo, data.strHtml, 3);

          setTimeout(function () {
            $('.modal-dialog').addClass('modal-full-screen');
            $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").chosen("destroy");
            $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").prop("selectedindex", -1);
            $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").chosen();
            setInitFunctions();

            $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SOA_Percentual2").prop("disabled", data.editar);
            $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SOA_Percentual2").trigger("chosen:updated");

            $("#SOA_Percentual2").blur(function (){
              calcularPercentualItensApropriacoes();
            });

            $("#ORC_ID2").change(function () {
              $("#SGP_Saldo, #OCI_ID2").html("");
              $("#OCI_ID2").append("<option value=''>" + strSelecione + "</option>");

              var valor = $.trim(this.value);
              if (valor != "") {
                $.ajax({
                  url: $.trim($("#orcamentos_itens").val()) + "/" + valor,
                  dataType: "json",
                  cache: false,
                  data: {
                    SGP_Informacoes: true,
                  },
                  type: "POST",
                }).success(function (data) {
                    $(".btn-formulario").prop("disabled", false);

                    if (data.error) {
                      dialogAlert(strAtencao, data.error.msg, 6);
                      return;
                    }

                    var strHtml = "";
                    for (var i = 0; i < data.arrDados.length; i++) {
                      strHtml += "<option ";

                      if (data.arrDados.length == 1) {
                        strHtml += " selected ";
                      }

                      strHtml += " value='"+data.arrDados[i].OCI_ID +"'>"+data.arrDados[i].OCI_Codigo+" - " +data.arrDados[i].OCI_Descricao+"</option>";
                    }

                    $("#OCI_ID2").append(strHtml);
                    $("#OCI_ID2").trigger("change");
                    $("#OCI_ID2").trigger("chosen:updated");
                  }).fail(function (data) {
                    $(".btn-formulario").prop("disabled", false);
                    $("#OCI_ID2").trigger("chosen:updated");
                    dialogAlert(strAtencao, data.responseText, 6);
                  });
              }
            });

            $("#OCI_ID2").change(function () {
              $("#SGP_Saldo").html('');              

              if ($.trim(this.value) != "") {
                $.post($.trim($("#orcamentos_plano_financeiro_por_item_orcamento").val()),
                  {
                    ORC_ID: $("#ORC_ID2").val(),
                    OCI_ID: $.trim(this.value),
                  },
                  function (data) {
                    if (data.sucesso == "true"){
                      $("#PLF_Conta2").val(data.PLF_Conta);
                    }

                    $("#SGP_Saldo").removeClass("label-success label-danger");

                    if (parseFloat(data.douSaldo) > 0){
                      $("#SGP_Saldo").addClass("label label-success");
                    }else{
                      $("#SGP_Saldo").addClass("label label-danger");
                    }

                    $("#SGP_Saldo").html(data.douSaldo);
                    
                    
                    $("#PLF_Conta2").trigger("chosen:updated");
                  },
                  "json"
                );
              }
            });

            calcularPercentualItensApropriacoes();
            // calcularPercentualItensApropriacoesAdicionar();
            consultarItemApropriacoes(itemSolicitacaoID);
            preLoadingClose();
          }, 500);
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  });
}

function novoItemSolicitacoes(solicitacaoID) {
  $("#btnInsumo").prop("disabled", true);
  var strLabel = $("#btnInsumo").html();
  $("#btnInsumo").html(strCarregando);

  $.post(
    $.trim($("#hddNovoItensSolicitacoes").val()),
    {
      SOL_ID: $.trim(solicitacaoID),
      EMP_Info: $.trim($("#EMP_Dados").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (!($("#dialogInsumos").data("bs.modal") || {}).isShown) {
          dialogAlert2(data.strTitulo, data.strHtml, 3, "dialogInsumos");
        }

        setTimeout(function () {
          $("#UNM_ID").chosen();
          setInitFunctions();
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
      }
      $("#btnInsumo").prop("disabled", false);
      $("#btnInsumo").html(strLabel);
    }, "json"
  );
}

function salvarItemApropriacao() {
  if ($.trim($("#CEN_ID").val()) == "") {
    $.notify("Centro de custo precisa ser informado.", "warn");
  } else if ($.trim($("#PLF_Conta2").val()) == "") {
    $.notify("Plano Financeiro precisa ser informado.", "warn");
  } else if ($.trim($("#SOA_Percentual2").val()) == "") {
    $.notify("Percentual da apropriação precisa ser informado.", "warn");
  } else if (
    $.trim($("#checkOrcamentoObrigatorio").val()) == "S" &&
    $.trim($("#ORC_ID2").val()) == ""
  ) {
    $.notify("Orçamento precisa ser informado.", "warn");
  } else if (
    $.trim($("#checkOrcamentoObrigatorio").val()) == "S" &&
    $.trim($("#OCI_ID2").val()) == ""
  ) {
    $.notify("Item do Orçamento precisa ser informado.", "warn");
  } else {
    $("#btnSalvarItemApropriacao").prop("disabled", true);
    var strLabel = $("#btnSalvarItemApropriacao").html();
    $("#btnSalvarItemApropriacao").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddApropriacoesSalvar").val()),
      dataType: "json",
      cache: false,
      data: {
        SOA_ID: $.trim($("#SOA_ID").val()),
        SOL_ID: $.trim($("#SOL_ID").val()),
        CEN_ID: $.trim($("#CEN_ID").val()),
        SIT_ID: $.trim($("#hdditemSolicitacaoID").val()),
        ORC_ID: $.trim($("#ORC_ID2").val()),
        OCI_ID: $.trim($("#OCI_ID2").val()),
        PLF_Conta: $.trim($("#PLF_Conta2").val()),
        SOA_Percentual: $.trim($("#SOA_Percentual2").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarItemApropriacao").html(strLabel);
        $("#btnSalvarItemApropriacao").prop("disabled", false);

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SOA_Percentual2").val("");
        $("#OCI_ID2").html("<option value=''>" + strSelecione + "</option>");
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").trigger("chosen:updated");

        $("#div-total").removeClass("has-success");
        $("#div-total").removeClass("has-warning");
        $("#div-total").removeClass("has-error");
        $("#label-total").removeClass("has-success");
        $("#label-total").removeClass("has-warning");
        $("#label-total").removeClass("has-error");
        var label = $("#label-total").html();
        $("#label-total").html(label);
        $("#SOA_PercentualTotal").val(data.total);

        if ($.trim($("#SOA_ID").val()) != "") {
          $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SOA_Percentual2").prop(
            "disabled",
            true
          );
          $(
            "#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SOA_Percentual2"
          ).trigger("chosen:updated");
        }

        consultarItemApropriacoes($.trim($("#hdditemSolicitacaoID").val()));
      })
      .fail(function (data) {
        $("#btnSalvarItemApropriacao").html(strLabel);
        $("#btnSalvarItemApropriacao").prop("disabled", false);
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function salvarItemSolicitacao() {
  if ($.trim($("#INS_ID").val()) == "") {
    $.notify("Insumo precisa ser informado.", "warn");
  } else if ($.trim($("#UNM_ID").val()) == "") {
    $.notify("Unidade de medida precisa ser informado.", "warn");
  } else if ($.trim($("#SIT_QuantidadeSolicitadas").val()) == "") {
    $.notify("Quantidade solicitada precisa ser informada.", "warn");
  } else {
    $("#btnSalvarItemSolicitacao").prop("disabled", true);
    var strLabel = $("#btnSalvarItemSolicitacao").html();
    $("#btnSalvarItemSolicitacao").html(strCarregando);

    $.post(
      $.trim($("#hddSalvarItensSolicitacoes").val()),
      {
        SOL_ID: $.trim($("#SOL_ID").val()),
        INS_ID: $.trim($("#INS_ID").val()),
        UNM_ID: $.trim($("#UNM_ID").val()),
        SIT_Detalhes: $.trim($("#SIT_Detalhes").val()),
        SIT_QuantidadeSolicitadas: $.trim(
          $("#SIT_QuantidadeSolicitadas").val()
        ),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");

          $(
            "#INS_ID, #INS_Codigo, #INS_Pesquisar, #UNM_ID, #SIT_Detalhes, #SIT_QuantidadeSolicitadas"
          ).val("");
          $("#UNM_ID").trigger("chosen:updated");

          consultarItensSolicitacoes($.trim($("#SOL_ID").val()));
        } else {
          $.notify(data.mensagem, "error");
        }

        $("#btnSalvarItemSolicitacao").prop("disabled", false);
        $("#btnSalvarItemSolicitacao").html(strLabel);
      },
      "json"
    );
  }
}

function atualizarItensApropriacoes(itensApropriacaoID, campo, valor) {
  if ($.trim(campo) != "" && $.trim(valor) != "") {
    $.post(
      $.trim($("#hddApropriacoesAtualizar").val()) + "/" + itensApropriacaoID,
      {
        SOL_ID: $.trim($("#SOL_ID").val()),
        strCampo: campo,
        strValor: valor,
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          if (campo == "SOA_Percentual") {
            consultarItensSolicitacoes($.trim($("#SOL_ID").val()));
          }

          calcularPercentualItensApropriacoes();

          $.notify(data.mensagem, "success");
        } else {
          $.notify(data.mensagem, "warn");
        }
      },
      "json"
    );
  }
}

function atualizarItensSolicitacoes(itensSolicitacaoID, campo, valor, totalID) {
  $.post(
    $.trim($("#hddAtualizarItensSolicitacoes").val()) +
    "/" +
    itensSolicitacaoID,
    {
      SOL_ID: $.trim($("#SOL_ID").val()),
      strCampo: campo,
      strValor: valor,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (campo == "SIT_QuantidadeSolicitadas") {
          $("#" + totalID).val(valor);
          //consultarItensSolicitacoes($.trim($('#SOL_ID').val()));
        }

        //$.notify(data.mensagem, "success");
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function retornarDadosItemOrcamento(itemID){
  if ($.trim(itemID) != ""){
    $.post(
      $.trim($("#hddDadosItemApropriacoes").val()) + "/" + itemID,
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#PLF_Conta2").val(data.arrDados[0].PLF_Conta);
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  }

}

function exibirItensSolicitacoes(itemID, strDetalhe, editar) {
  $("#hddCodigoSelecionado").val(itemID);

  $.post(
    $.trim($("#hddExibirDetalheItemSolicitacoes").val()),
    {
      SIT_ID: itemID,
      SIT_Detalhes: strDetalhe,
      strEditar: editar,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert("Visualização de Detalhes", data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function exibirDetalhesMedicoes(itemID, strDetalhe, editar) {
  $("#hddCodigoSelecionado").val(itemID);

  $.post(
    $.trim($("#hddMedicoesExibirDetalhes").val()),
    {
      MED_ID: itemID,
      MED_Detalhes: strDetalhe,
      strEditar: editar,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert("Visualização de Detalhes", data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarDetalhesMedicao() {
  $.post(
    $.trim($("#hddMedicoesSalvarDetalhes").val()),
    {
      MED_ID: $.trim($("#hddCodigoSelecionado").val()),
      MED_Detalhes: $.trim($("#MED_Detalhe").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $.notify(data.mensagem, "success");
        $("#btnCloseDialogAlert").trigger("click");

        consultarMedicoes();
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarDetalheItemSolicitacao() {
  $.post(
    $.trim($("#hddSalvarDetalheItemSolicitacoes").val()),
    {
      SIT_ID: $.trim($("#hddCodigoSelecionado").val()),
      SIT_Detalhes: $.trim($("#SIT_Detalhes").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $.notify(data.mensagem, "success");
        $("#btnCloseDialogAlert").trigger("click");
        consultarItensSolicitacoes($.trim($("#SOL_ID").val()));
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function consultarItensCotacoes(cotacaoID) {
  $("#itens-cotacoes").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddConsultarItensCotacoes").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      COT_ID: $.trim(cotacaoID),
    },
  })
    .success(function (data) {
      if (data.error) {
        $("#itens-cotacoes").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#itens-cotacoes").html(data.strHtml);

      if (data.status == "F") {
        $(
          "#btnAprovar, #btnReprovar, #btnItens, #btnDescontos, #btnDesmarcar, #btnExcluir, #btnAdicionarFornecedores"
        ).hide();
      } else {
        if (data.aprovacao) {
          if (data.aprovado == true) {
            $("#btnAprovar").hide();
            $("#btnReprovar").show();
          }

          if (data.reprovado == true) {
            $("#btnAprovar").show();
            $("#btnReprovar").hide();
          }

          if (data.aprovado == null && data.reprovado == null) {
            $("#btnAprovar").show();
            $("#btnReprovar").hide();
          } else {
            if (data.aprovado == true && data.dadosAprovado != null) {
              $("#dados-aprovado").show();
              $("#dados-reprovado").hide();
              $("#spnAprovado").html(data.dadosAprovado);
              // $('#btnFinalizar').prop('disabled', true);
              $(
                "#btnReprovar, #btnFinalizar, #btnItens, #btnDescontos, #btnDesmarcar, #btnExcluir, #btnAdicionarFornecedores"
              ).prop("disabled", false);
            }

            if (data.reprovado == true && data.dadosReprovado != null) {
              $("#dados-reprovado").show();
              $("#dados-aprovado").hide();
              $("#spnReprovado").html(data.dadosReprovado);
              $(
                "#btnAprovar, #btnItens, #btnDescontos, #btnDesmarcar, #btnExcluir, #btnAdicionarFornecedores"
              ).prop("disabled", false);
              $("#btnReprovar, #btnFinalizar").prop("disabled", true);
            }
          }
        } else {
          $("#btnFinalizar").prop("disabled", false);
        }
      }

      setInitFunctions();

      $("#spnTotalRegistros").html(data.totalRegistros);
      $('body').css('overflow-y','scroll');

      if ($.trim($("#hddTemFornecedor").val()) == strSim) {
        $(".exibirEsconder").hide();
      }
    })
    .fail(function (data) {
      $("#itens-cotacoes").html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function atualizarItensCotacoes(
  itemCotacaoID,
  strCampo,
  strValor,
  quantidadeSolicitada
) {

  if ($.trim(strValor) == "0,00000" || $.trim(strValor) == "" && $.trim(strCampo) !== 'CIT_Integracao') {
    $.notify("Quantidade precisa ser informada e precisa ser maior que zero.", "warn");
    return;
  }

  if($.trim(strValor) == "" && $.trim(strCampo) == 'CIT_Integracao'){
    $.notify("Itens Precisam ser Vinculados a uma Integração.", "warn");
    return;
  }

  $("#btn-formulario").prop("disabled", true);

  $.ajax({
    url: $.trim($("#hddAtualizarItensCotacoes").val()),
    dataType: "json",
    cache: false,
    data: {
      COT_ID: $.trim($("#COT_ID").val()),
      CIT_ID: $.trim(itemCotacaoID),
      strCampo: strCampo,
      strValor: strValor,
      CIT_Quantidade: quantidadeSolicitada,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      setInitFunctions();
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);

      dialogAlert(strAtencao, data.responseText, 6);
    });
  
}

function atualizarItensCotacoesFornecedores(
  itemFornecedorID,
  strValor,
  intIndice
) {
  $(document).ready(function () {
    $.post(
      $.trim($("#hddAtualizarItensCotacoesFornecedores").val()),
      {
        CIF_ID: $.trim(itemFornecedorID),
        strValor: strValor,
        CIT_Quantidade: $.trim($("#CIT_Quantidade_" + intIndice).val()),
        intIndice: intIndice,
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          //$.notify(data.mensagem, "success");

          $("#CIF_ValorTotal_" + intIndice).val(data.total);

          consultarItensCotacoes($.trim($("#COT_ID").val()));
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  });
}

function removerInsumosSelecionados() {
  var arrSelecionados = new Array();

  $("input[type=checkbox][name='items[]']:checked").each(function () {
    arrSelecionados.push($(this).val());
  });

  if (arrSelecionados.length > 0) {
    if (confirm("Confirma a exclusão dos itens selecionados ?")) {
      $.post(
        $.trim($("#hddRemoverItensCotacoes").val()),
        {
          COT_ID: $.trim($("#COT_ID").val()),
          CIT_ID: arrSelecionados,
        },
        function (data) {
          //alert(data); return;
          if (data.sucesso == "true") {
            $.notify(data.mensagem, "success");
            consultarItensCotacoes($("#COT_ID").val());
          } else {
            $.notify(data.mensagem, "error");
          }
        },
        "json"
      );
    }
  } else {
    $.notify("Selecione no minímo um item para exclusão", "error");
  }
}

function calcularItensFornecedores(identificador, fornecedorID, quantidade, valor, inputValorTotal){ 
    $.ajax({
      url: $.trim($("#hddCalcularItensCotacoes").val()),
      dataType: "json",
      cache: false,
      data: {
        COT_ID: $.trim($("#COT_ID").val()),
        CIT_ID: identificador,
        ENT_ID: fornecedorID,
        CIT_Quantidade: $("#" + quantidade).val(),
        CIF_ValorUnitario: valor
      },
      type: "POST",
    }).success(function (data){ 
        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $('#'+inputValorTotal).val(data.douValorTotal);
        $('#totalFornecedor_'+data.fornecedorID).html(data.douValorTotalFornecedor);
        $('#tdValorTotalSelecionado').html(data.douValorTotalGeral);
        
    }).fail(function (data){
        dialogAlert(strAtencao, data.responseText, 6);
    });
}

function selecionarItensFornecedores(identificador, cotacaoID, entidadeID) {
  preLoadingOpen();
  
  $.ajax({
    url: $.trim($("#hddSelecionarItensCotacoes").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      CIT_ID: identificador,
      COT_ID: cotacaoID,
      ENT_ID: entidadeID,
    },
  }).success(function (data) {
    if (data.error) {
      preLoadingClose();
      dialogAlert(strAtencao, data.error.msg, 6);
      return;
    }

    // $("#tdValorTotalDescontado").html(data.douValorTotalDescontoSelecionado);
    // $("#tdValorTotalSelecionado").html(data.douValorTotalSelecionado);
    $('#btnAtualizar').trigger('click');
    $.notify(data.mensagem, "success");
    preLoadingClose();
    
  }).fail(function (data){
    console.log('fail', data);
      preLoadingClose();
      $("#tdValorTotalDescontado").html("");
      $("#tdValorTotalSelecionado").html("");
      dialogAlert(strAtencao, data.responseText, 6);
  });
}

function confirmarCotacao() {
  $("#linkConfirmarPadrao").prop("disabled", false);
  var intTotalRegistros = parseInt($("#hddTotalRegistros").val());
  var arrItensFornecedoresSelecionados = new Array();

  $("input[type='radio']:checked").each(function () {
    arrItensFornecedoresSelecionados.push($(this).val());
  });

  var bolValidar = true;
  $("select[name='CIT_Integracao[]']").each(function () {
    if ($.trim($(this).val()) == "") {
      bolValidar = false;
    }
  });

  if (
    arrItensFornecedoresSelecionados.length > 0 &&
    intTotalRegistros == arrItensFornecedoresSelecionados.length &&
    bolValidar
  ) {
    $(".btn-formulario").prop("disabled", true);
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#hddConfirmarCotacao").val()),
      dataType: "json",
      cache: false,
      data: {
        COT_ID: $.trim($("#COT_ID").val()),
        COT_NumeroCotacao: $.trim($("#COT_NumeroCotacao").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();

        if (data.error) {
          $("#linkConfirmarPadrao").prop("disabled", false);
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $("#confirm-padrao-titulo").html(data.label_confirmar);
        $("#confirm-padrao-descricao").html(data.descricao_confirmar);
        $("#confirm-padrao").modal("toggle");

        $("#linkConfirmarPadrao").one("click", function (e) {
          $("#linkConfirmarPadrao").prop("disabled", true);
          finalizarCotacao();
        });
      })
      .fail(function (data) {
        $("#linkConfirmarPadrao").prop("disabled", false);
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  } else {
    $.notify("Itens precisam ser vinculados a uma integração.", "warn");

    setTimeout(function () {
      $("#confirm-padrao").modal("hide");
    }, 100);
  }
}

function finalizarCotacao() {
  $(".btn-formulario").prop("disabled", true);
  preLoadingOpen();

  var intTotalRegistros = parseInt($("#hddTotalRegistros").val());
  var arrItensFornecedoresSelecionados = new Array();
  var arrIntegracoesSelecionadas = new Array();

  $("input[type=radio]:checked").each(function () {
    arrItensFornecedoresSelecionados.push($(this).val());
  });

  $("select[name='CIT_Integracao[]']").each(function () {
    if ($.trim($(this).val()) != "") {
      arrIntegracoesSelecionadas.push($(this).val());
    }
  });

  if (
    arrIntegracoesSelecionadas.length > 0 &&
    arrItensFornecedoresSelecionados.length > 0 &&
    intTotalRegistros == arrItensFornecedoresSelecionados.length &&
    arrItensFornecedoresSelecionados.length == arrIntegracoesSelecionadas.length
  ) {
    var arrItensCotacoes = new Array();
    $(".marcar").each(function () {
      arrItensCotacoes.push($(this).val());
    });

    $.ajax({
      url: $.trim($("#hddFinalizarCotacao").val()),
      dataType: "json",
      cache: false,
      data: {
        COT_ID: $.trim($("#COT_ID").val()),
        EMP_ID: $.trim($("#EMP_ID").val()),
        CIT_ID: arrItensCotacoes,
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", true);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $("#confirm-padrao").modal("toggle");
        $.notify(data.mensagem, "success");

        setTimeout(function () {
          redir("");
        }, 1000);
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", true);
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  } else {
    preLoadingClose();
    $("#linkConfirmarPadrao").prop("disabled", false);
    $("button").prop("disabled", false);
    $("#confirm-padrao").modal("toggle");
    $.notify(strTodasOpcoes, "error");
  }
}

function enterPesquisarPedidos(e) {
  if (e.keyCode == 13) {
    if (
      $.trim($("#PED_Numero").val()) != "" ||
      $.trim($("#DOC_Numero").val()) != "" ||
      $.trim($("#COT_NumeroCotacao").val()) != ""
    ) {
      $("#txtDataInicial, #txtDataFinal").val("");
    }

    consultarPedidos();
  }
}

function consultarPedidos(){
  var strLabel        = consultarPadraoInicial();
  var arrEmpresas     = new Array();
  var arrFornecedores = new Array();
  var arrUsuarios     = new Array();
  var arrInsumos      = new Array();
  var arrAprovados    = new Array();
  var arrObras        = new Array();
  var arrEstoque      = new Array();
  var arrFaturamento  = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='ENT_ID[]'] option:selected").each(function () {
    arrFornecedores.push($(this).val());
  });

  $("select[name='USU_ID[]'] option:selected").each(function () {
    arrUsuarios.push($(this).val());
  });

  $("select[name='INS_ID[]'] option:selected").each(function () {
    arrInsumos.push($(this).val());
  });

  $("select[name='SGP_Aprovado[]'] option:selected").each(function () {
    arrAprovados.push($(this).val());
  });

  $("select[name='CAX_Obra_ID[]'] option:selected").each(function () {
    arrObras.push($(this).val());
  });

  $("select[name='PED_Estoque[]'] option:selected").each(function () {
    arrEstoque.push($(this).val());
  });

  $("select[name='PED_FaturamentoDireto[]'] option:selected").each(function () {
    arrFaturamento.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#hddConsultarPedidos").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: arrEmpresas,
      ENT_ID: arrFornecedores,
      INS_ID: arrInsumos,
      USU_Cadastro_ID: arrUsuarios,
      PED_Numero: $.trim($("#PED_Numero").val()),
      COT_NumeroCotacao: $.trim($("#COT_NumeroCotacao").val()),
      DOC_Numero: $.trim($("#DOC_Numero").val()),
      PED_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      PED_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
      SGP_Pesquisar: $.trim($("#PED_Pesquisar").val()),
      PED_Situacao: $.trim($("#PED_Situacao").val()),
      SGP_Aprovado: arrAprovados,
      CAX_Obra_ID: arrObras,
      PED_Estoque: arrEstoque,
      PED_FaturamentoDireto: arrFaturamento
    },
    type: "POST",
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);
      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
      $(".btn-impressao").hide();
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarItensPedidos(pedidoID, arrSelecionados = null) {
  $(".btn-formulario").prop("disabled", true);
  $("#itens-pedidos").html(strCarregando);

  var strVisualizar = $.trim($("#hddNao").val());
  if ($.trim($("#hddVisualizar").val()) != "") {
    strVisualizar = $.trim($("#hddSim").val());
  }

  $.ajax({
    url: $.trim($("#hddConsultarItensPedidos").val()),
    dataType: "json",
    cache: false,
    data: {
      PED_ID: $.trim(pedidoID),
      ENT_ID: $.trim($("#ENT_ID").val()),
      arrSelecionados: arrSelecionados,
      strVisualizar: strVisualizar,
    },
    type: "POST",
  }).success(function (data) {
    $(".btn-formulario").prop("disabled", false);
    $("#btnGerar").prop("disabled", true);

    if (data.error) {
      dialogAlert(strInformacao, data.error.msg, 6);
      return;
    }

    if (data.intTotalRegistros > 0) {
      $("#btnGerar").prop("disabled", false);
    }

    $("#itens-pedidos").html(data.strHtml);
    $("#spnTotalRegistros").html(data.intTotalRegistros);
    setInitFunctions();
  }).fail(function (data) {
    $(".btn-formulario").prop("disabled", false);
    dialogAlert(strAtencao, data.responseText, 6);
  });
}

function novoItemPedidos(pedidoID, reprovar = false) {
  $("#hddExecutar").val("S");

  $.ajax({
    url: $.trim($('#hddNovoItensPedidos').val()),
    dataType: 'json',
    cache: false,
    data: {
      PED_ID: $.trim(pedidoID),
      reprovar: reprovar
    },
    type: 'POST',
  }).success(function (data) {
    $('.btn-formulario, .btn-filtro').prop('disabled', false);

    if (data.error) {
      $('#INS_Codigo, #INS_Pesquisar').val('');
      dialogAlert(strInformacao, data.error.msg, 6);
      return;
    }
    
    dialogAlert2(data.strTitulo, data.strHtml, 3, "dialogNovoItemPedido");

    setTimeout(function () {
      $('#INS_Codigo').focus();
      setInitFunctions();
    }, 1000);

  }).fail(function (data) {
    $('#INS_Codigo, #INS_Pesquisar').val('');
    $('.btn-formulario, .btn-filtro').prop('disabled', false);
    dialogAlert(strAtencao, data.responseText, 6);
  });
}

function salvarItemPedido(reprovar) {
  if ($.trim($("#INS_ID").val()) == "") {
    $.notify("Insumo precisa ser informado.", "warn");
  } else if ($.trim($("#UNM_ID").val()) == "") {
    $.notify("Unidade de medida precisa ser informado.", "warn");
  } else if ($.trim($("#PDI_Quantidade").val()) == "") {
    $.notify("Quantidade precisa ser informada.", "warn");
  } else if ($.trim($("#PDI_ValorUnitario").val()) == "") {
    $.notify("Valor unitário precisa ser informada.", "warn");
  } else {
    $("#btnSalvarItemPedido").prop("disabled", true);

    $.post(
      $.trim($("#hddItensPedidosSalvar").val()),
      {
        PED_ID: $.trim($("#PED_ID").val()),
        INS_ID: $.trim($("#INS_ID").val()),
        UNM_ID: $.trim($("#UNM_ID").val()),
        PDI_Detalhes: $.trim($("#PDI_Detalhes").val()),
        PDI_Quantidade: $.trim($("#PDI_Quantidade").val()),
        PDI_ValorUnitario: $.trim($("#PDI_ValorUnitario").val()),
        PDI_ValorTotal: $.trim($("#PDI_ValorTotal").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#btnSalvarItemPedido").prop("disabled", false);
          $.notify(data.mensagem, "success");

          $("#UNM_ID").val("");
          $("#INS_ID").val("");
          $("#INS_Codigo").val("");
          $("#INS_Pesquisar").val("");
          $("#INS_Pesquisar2").val("");
          $("#PDI_Detalhes").val("");
          $("#PDI_Quantidade").val("");
          $("#PDI_ValorUnitario").val("");
          $("#PDI_ValorTotal").val("");

          consultarItensPedidos($.trim($("#PED_ID").val()));

          if(reprovar == 'true'){
            reprovarPedidoDireto($.trim($('#decode').val()));
          }

        } else {
          $("#btnSalvarItemPedido").prop("disabled", false);
          $.notify(data.mensagem, "error");
        }
        $("#hddExecutar").val("1");
      },
      "json"
    );
  }
}

function calcularItensPedidosValores() {
  $.post(
    $.trim($("#hddCalcularItensPedidos").val()),
    {
      PDI_Quantidade: $.trim($("#PDI_Quantidade").val()),
      PDI_ValorUnitario: $.trim($("#PDI_ValorUnitario").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#PDI_ValorTotal").val(data.total);
      }
    },
    "json"
  );
}

function exibirDetalhesItensPedidos(itemID, strDetalhe, editar) {
  $("#hddCodigoSelecionado").val(itemID);

  $.post(
    $.trim($("#hddItensPedidosExibirDetalhes").val()),
    {
      PDI_ID: itemID,
      PDI_Detalhes: strDetalhe,
      strEditar: editar,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert("Visualização de Detalhes", data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarDetalheItemPedido() {
  $(".btn-formulario").prop("disabled", true);
  var strLabel = $("#btnSalvarDialog").html();
  $("#btnSalvarDialog").html(strCarregando);

  $.ajax({
    url: $.trim($("#itens_pedidos_salvar_detalhe").val()),
    dataType: "json",
    cache: false,
    data: {
      PED_ID: $.trim($("#PED_ID").val()),
      PDI_ID: $.trim($("#hddCodigo").val()),
      PDI_Detalhes: $.trim($("#PDI_Detalhes").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvarDialog").html(strLabel);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $(".modal").modal("hide");

      $.notify(data.mensagem, "success");
      consultarItensPedidos($.trim($("#PED_ID").val()));
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvarDialog").html(strLabel);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function atualizarItensPedidos(itemID, campo, valor, calcular, totalID,saldoID) {
  $(".btn-formulario").prop("disabled", true);
  $("#hddCodigoSelecionado").val("");
  $("#tdValorTotalInsumos").html(strCarregando);
  $("#tdValorTotal").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddItensPedidosAtualizar").val()) + "/" + $.trim(itemID),
    dataType: "json",
    cache: false,
    data: {
      PED_ID: $.trim($("#PED_ID").val()),
      strCampo: campo,
      strValor: valor,
      strCalcular: calcular,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#" + totalID).html(data.douValorTotal);
      $("#" + saldoID).html(data.douValorSaldo);
      $("#tdValorTotalInsumos").html(data.douValorInsumos);
      $("#tdValorTotal").html(data.douValorGeral);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function calcularComprasPedidosApropriacoesPercentual() {
  $("#btnSalvarItemApropriacao").prop("disabled", true);
  $("#SGP_PercentualTotal").val("");

  $.ajax({
    url: $.trim($("#itens_pedidos_apropriacoes_calcular").val()),
    dataType: "json",
    cache: false,
    data: {
      PED_ID: $.trim($("#PED_ID").val()),
      PDI_ID: $.trim($("#hddCodigo").val()),
      SGP_Percentual: $.trim($("#SGP_Percentual").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        $("#btnSalvarItemApropriacao").prop("disabled", false);
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      var label = $("#label-total").html();
      $("#SGP_PercentualTotal").val(data.total);
      $("#div-total").removeClass("has-success");
      $("#div-total").removeClass("has-warning");
      $("#div-total").removeClass("has-error");
      $("#label-total").removeClass("has-success");
      $("#label-total").removeClass("has-warning");
      $("#label-total").removeClass("has-error");

      if (parseFloat(data.total_sem_mascara) > 100) {

        $("#div-total").addClass("has-error");
        $("#label-total").addClass("has-error");

      }else if (parseFloat(data.total_sem_mascara) == 100.0){

        $("#div-total").addClass("has-success");
        $("#label-total").addClass("has-success");
        $("#btnSalvarItemApropriacao").prop("disabled", false);
      }else{

        $("#div-total").addClass("has-warning");
        $("#label-total").addClass("has-warning");
        $("#btnSalvarItemApropriacao").prop("disabled", false);

      }

      $("#label-total").html(label);
    }).fail(function (data) {
      $("#btnSalvarItemApropriacao").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarItemPedidoApropriacao() {
  if ($.trim($("#CEN_ID").val()) == "") {
    $.notify("Centro de custo precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#PLF_Conta2").val()) == "") {
    $.notify("Plano Financeiro precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SGP_Percentual").val()) == "") {
    $.notify("Percentual da apropriação precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#checkOrcamentoObrigatorio").val()) == "S" &&
    $.trim($("#ORC_ID2").val()) == ""
  ) {
    $.notify("Orçamento precisa ser informado.", "warn");
  } else if (
    $.trim($("#checkOrcamentoObrigatorio").val()) == "S" &&
    $.trim($("#OCI_ID2").val()) == ""
  ) {
    $.notify("Item do Orçamento precisa ser informado.", "warn");
  } else {
    $("#btnSalvarItemApropriacao").prop("disabled", true);
    var strLabel = $("#btnSalvarItemApropriacao").html();
    $("#btnSalvarItemApropriacao").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddApropriacoesItemSalvar").val()),
      dataType: "json",
      cache: false,
      data: {
        PIA_ID: $.trim($("#PIA_ID").val()),
        PED_ID: $.trim($("#PED_ID").val()),
        CEN_ID: $.trim($("#CEN_ID").val()),
        PDI_ID: $.trim($("#hddCodigo").val()),
        ORC_ID: $.trim($("#ORC_ID2").val()),
        OCI_ID: $.trim($("#OCI_ID2").val()),
        PLF_Conta: $.trim($("#PLF_Conta2").val()),
        PIA_Percentual: $.trim($("#SGP_Percentual").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarItemApropriacao").prop("disabled", false);
        $("#btnSalvarItemApropriacao").html(strLabel);

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SGP_Percentual").val("");
        $("#OCI_ID2").html("<option value=''>" + strSelecione + "</option>");
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").trigger("chosen:updated");

        $("#div-total").removeClass("has-success");
        $("#div-total").removeClass("has-warning");
        $("#div-total").removeClass("has-error");
        $("#label-total").removeClass("has-success");
        $("#label-total").removeClass("has-warning");
        $("#label-total").removeClass("has-error");
        $("#label-total").html("Total Percentual Adicionado");
        $("#SGP_PercentualTotal").val(data.total);

        $.notify(data.mensagem, "success");

        if ($.trim($("#PIA_ID").val()) != "") {
          $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SGP_Percentual").prop(
            "disabled",
            true
          );
          $(
            "#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SGP_Percentual"
          ).trigger("chosen:updated");
        }

        consultarItemPedidoApropriacoes();
      })
      .fail(function (data) {
        $("#btnSalvarItemApropriacao").prop("disabled", false);
        $("#btnSalvarItemApropriacao").html(strLabel);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarItemPedidoApropriacoes() {
  $("#btnSalvarItemApropriacao").prop("disabled", true);
  var strLabel = $("#resultadoApropriacoes").html();
  $("#resultadoApropriacoes").html(strCarregando);

  $.ajax({
    url: $.trim($("#itens_pedidos_apropriacoes_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      PDI_ID: $.trim($("#hddCodigo").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $("#btnSalvarItemApropriacao").prop("disabled", false);
      $("#resultadoApropriacoes").html(strLabel);

      if (data.error) {
        $("#resultadoApropriacoes").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#resultadoApropriacoes").html(data.strHtml);
      $("#spnTotalRegistros").html(data.totalRegistros);
    })
    .fail(function (data) {
      $("#btnSalvarItemApropriacao").prop("disabled", false);
      $("#resultadoApropriacoes").html(strLabel);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function atualizarCorretorComprado(corretorID, terrenoID) {
  $.post(
    $.trim($("#hddCorretoresAtualizarComprado").val()),
    {
      TCO_ID: $.trim(corretorID), //sequencial
      TER_ID: $.trim(terrenoID),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $.notify(data.mensagem, "success");
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function confirmarAprovacaoPedido(pedidoID, pedidoNumero) {
  $(document).ready(function () {
    $("#hddCodigoSelecionado").val("");

    $.post(
      $.trim($("#hddPedidosConfirmar").val()),
      {
        PED_ID: $.trim(pedidoID),
        PED_Numero: $.trim(pedidoNumero),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#confirm-padrao-titulo").html(data.label_confirmar);
          $("#confirm-padrao-descricao").html(data.descricao_confirmar);

          $("#linkConfirmarPadrao").one("click", function (e) {
            $("#hddCodigoSelecionado").val(pedidoID);
            $("#linkConfirmarPadrao").prop("disabled", true);

            aprovarPedido();
          });
        } else {
          $("#confirm-padrao").modal("toggle");
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  });
}
function aprovarPedido() {
  $.post(
    $.trim($("#hddPedidosAprovar").val()) +
    "/" +
    $.trim($("#hddCodigoSelecionado").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $.notify(data.mensagem, "success");

        if ($.trim($("#PED_ID").val()) != "") {
          setTimeout(function () {
            redir("../Visualizar/" + $.trim(data.codigo), "parent");
          }, 1000);
        } else {
          $("#linkConfirmarPadrao").prop("disabled", false);
          consultarPedidos();
        }
      } else {
        $.notify(data.mensagem, "error");
      }

      $("#linkConfirmarPadrao").prop("disabled", false);
      $("#confirm-padrao").modal("toggle");
    },
    "json"
  );
}
function confirmarReprovacaoPedido(pedidoID, pedidoNumero) {
  $(document).ready(function () {
    $("#linkConfirmarPadrao").prop("disabled", false);
    $("#hddCodigoSelecionado").val("");

    $.post(
      $.trim($("#hddPedidosConfirmarReprovar").val()),
      {
        PED_ID: $.trim(pedidoID),
        PED_Numero: $.trim(pedidoNumero),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#confirm-padrao-titulo").html(data.label_confirmar);
          $("#confirm-padrao-descricao").html(data.descricao_confirmar);

          $("#linkConfirmarPadrao").one("click", function (e) {
            $("#hddCodigoSelecionado").val(pedidoID);

            $("#linkConfirmarPadrao").prop("disabled", true);
            reprovarPedido();
          });
        } else {
          $("#confirm-padrao").modal("toggle");
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  });
}
function reprovarPedido() {
  $.post(
    $.trim($("#hddPedidosReprovar").val()) +
    "/" +
    $.trim($("#hddCodigoSelecionado").val()),
    function (data) {
      //alert(data); return;
      $("#confirm-padrao").modal("toggle");
      if (data.sucesso == "true") {
        $.notify(data.mensagem, "success");

        if ($.trim($("#PED_ID").val()) != "") {
          setTimeout(function () {
            redir("", "parent");
          }, 2000);
        } else {
          $("#linkConfirmarPadrao").prop("disabled", false);
          consultarPedidos();
        }
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarTarefasTerrenos() {
  if ($.trim($("#TAR_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else {
    $("#btnSalvarTarefas").prop("disabled", true);
    var strLabel = $("#btnSalvarTarefas").html();
    $("#btnSalvarTarefas").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddTerrenosTarefasSalvar").val()),
      dataType: "json",
      cache: false,
      data: {
        TER_ID: $.trim($("#TER_ID").val()),
        TAR_ID: $.trim($("#TAR_ID").val()),
        TAR_Descricao: $.trim($("#TAR_Descricao").val()),
        TAR_Observacoes: $.trim($("#TAR_Observacoes").val()),
        TAR_PercentualConcluido: 0,
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarTarefas").prop("disabled", false);
        $("#btnSalvarTarefas").html(strLabel);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $(
          "#TAR_ID, #TAR_Descricao, #TAR_Observacoes, #TAR_PercentualConcluido"
        ).val("");
        $(".modal").modal("hide");
        $.notify(data.mensagem, "success");
        consultarTerrenosTarefas();
      })
      .fail(function (data) {
        $("#btnSalvarTarefas").prop("disabled", false);
        $("#btnSalvarTarefas").html(strLabel);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function salvarTarefasTerrenosSubEtapa() {
  if ($.trim($("#TAR_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else {
    $("#btnSalvarTarefas").prop("disabled", true);
    var strLabel = $("#btnSalvarTarefas").html();
    $("#btnSalvarTarefas").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddTerrenosTarefasSalvar").val()),
      dataType: "json",
      cache: false,
      data: {
        TER_ID: $.trim($("#TER_ID").val()),
        // TAR_ID: $.trim($('#TAR_ID').val()),
        TAR_Codigo: $.trim($("#hddTarefaCodigo").val()),
        TAR_Descricao: $.trim($("#TAR_Descricao").val()),
        TAR_Observacoes: $.trim($("#TAR_Observacoes").val()),
        TAR_PercentualConcluido: "0",
        TIPO: "subetapa",
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarTarefas").prop("disabled", false);
        $("#btnSalvarTarefas").html(strLabel);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $(
          "#TAR_ID, #TAR_Descricao, #TAR_Observacoes, #TAR_PercentualConcluido"
        ).val("");
        $(".modal").modal("hide");
        $.notify(data.mensagem, "success");
        consultarTerrenosTarefas();
      })
      .fail(function (data) {
        $("#btnSalvarTarefas").prop("disabled", false);
        $("#btnSalvarTarefas").html(strLabel);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarTerrenosTarefas() {
  preLoadingOpen();

  $(
    "#divCorretores, #divTerrenosEstudos, #divProprietarios, #divTerrenosViabilidades, #divDocumentos, #divObservacoes, #tab_log"
  ).html("");
  $("#boxTarefas").show();
  $("#divTarefas").html(strCarregando);

  $.post(
    $.trim($("#hddTerrenosTarefasConsultar").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      visualizar: $("#hddVisualizarTerreno").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.totalRegistros > 0) {
          $("#divTarefas").html(data.strHtml);
        } else {
          $("#boxTarefas").hide();
          $("#divTarefas").html("");
        }
      } else {
        $("#boxTarefas").hide();
        $("#divTarefas").html("");
        $("#boxTarefas").hide();
      }
      preLoadingClose();
    },
    "json"
  );
}

function consultarTerrenosTarefasVisualizar() {
  preLoadingOpen();

  $("#divCorretores").html("");
  $("#divTerrenosEstudos").html("");
  $("#divProprietarios").html("");
  $("#divTerrenosViabilidades").html("");
  $("#divDocumentos").html("");
  $("#divObservacoes").html("");
  $("#tab_log").html("");
  $("#boxTarefas").show();
  $("#divTarefas").html(strCarregando);

  $.post(
    $.trim($("#hddTerrenosTarefasConsultarVisualizar").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      visualizar: $("#hddVisualizarTerreno").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.totalRegistros > 0) {
          $("#divTarefas").html(data.strHtml);
        } else {
          $("#boxTarefas").hide();
          $("#divTarefas").html("");
        }
      } else {
        $("#boxTarefas").hide();
        $("#divTarefas").html("");
        $("#boxTarefas").hide();
      }
      preLoadingClose();
    },
    "json"
  );

  $("#divTarefas").html(strSemDados);
}

function adicionarTerrenosTarefasNovoItem(tarefaCodigo) {
  $.post(
    $.trim($("#hddTerrenosTarefasNovoItem").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      TAR_Codigo: $.trim(tarefaCodigo),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#btnCloseDialogAlert").hide();

        dialogAlert2(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          setInitFunctions();
          preLoadingClose();
        }, 1000);
      }
    },
    "json"
  );
}

function adicionarTerrenosTarefasSubEtapa(tarefaCodigo, idTarefa) {
  $.post(
    $.trim($("#hddTerrenosTarefasSubEtapa").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      TAR_ID: $.trim(idTarefa),
      TAR_Codigo: $.trim(tarefaCodigo),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#btnCloseDialogAlert").hide();

        dialogAlert2(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          setInitFunctions();
          preLoadingClose();
        }, 1000);
      }
    },
    "json"
  );
}

function editarTerrenosTarefas(tarefaID) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddTerrenosTarefasEditar").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
      TAR_ID: $.trim(tarefaID),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          $(".multiplos").multiselect(getOptions());
          preLoadingClose();
        }, 500);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarTarefasTerrenosItem() {
  var arrResponsaveis = new Array();
  $("select[name='USU_ID[]'] option:selected").each(function () {
    arrResponsaveis.push($(this).val());
  });

  if ($.trim($("#TAR_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#TAR_DataInicio").val()) == "") {
    $.notify("Data início precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#TAR_DataFim").val()) == "") {
    $.notify("Data final precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#TAR_DiasAntecedencia").val()) == "") {
    $.notify("Dias de antecedência precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#TAR_PercentualConcluido").val()) == "") {
    $.notify("Percentual precisa ser informado.", "warn");
    return;
  } else if (arrResponsaveis.length == 0) {
    $.notify("Mínimo 1 (UM) responsável precisa ser informado.", "warn");
    return;
  } else {
    $("#btnSalvarTarefas").prop("disabled", true);
    var strLabel = $("#btnSalvarTarefas").html();
    $("#btnSalvarTarefas").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddTerrenosTarefasSalvarItem").val()),
      dataType: "json",
      cache: false,
      data: {
        TAR_Codigo: $.trim($("#hddTarefaCodigo").val()),
        USU_ID: arrResponsaveis,
        TER_ID: $.trim($("#TER_ID").val()),
        TAR_ID: $.trim($("#TAR_ID").val()),
        TAR_Descricao: $.trim($("#TAR_Descricao").val()),
        TAR_Observacoes: $.trim($("#TAR_Observacoes").val()),
        TAR_DataInicio: $.trim($("#TAR_DataInicio").val()),
        TAR_DataFim: $.trim($("#TAR_DataFim").val()),
        TAR_DiasAntecedencia: $.trim($("#TAR_DiasAntecedencia").val()),
        TAR_PercentualConcluido: $.trim($("#TAR_PercentualConcluido").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarTarefas").html(strLabel);
        $("#btnSalvarTarefas").prop("disabled", false);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $(
          "#TAR_ID, #TAR_Descricao, #TAR_Observacoes, #TAR_DataInicio, #TAR_DataFim, #TAR_DiasAntecedencia, #TAR_PercentualConcluido"
        ).val("");

        $(".modal").modal("hide");
        $.notify(data.mensagem, "success");
        consultarTerrenosTarefas();
      })
      .fail(function (data) {
        $("#btnSalvarTarefas").html(strLabel);
        $("#btnSalvarTarefas").prop("disabled", false);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function calcularComprasContratosApropriacoesAditivos() {
  $("#btnSalvarItemApropriacao").prop("disabled", true);
  $("#div-total").removeClass("has-success has-warning has-error");
  $("#label-total").removeClass("has-success has-warning has-error");

  $.ajax({
    url: $.trim($("#compras_contratos_aditivos_apropriacoes_calcular").val()),
    dataType: "json",
    cache: false,
    data: {
      CON_ID: $.trim($("#CON_ID").val()),
      CAI_ID: $.trim($("#hddCodigoAditivo").val()),
      CAA_Percentual: $.trim($("#SGP_Percentual").val()),
    },
    type: "POST",
  }).success(function (data) {
      $("#btnSalvarItemApropriacao").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      if (data.status == "maior") {
        $("#div-total").addClass("has-error");
        $("#label-total").html(
          "<i class='fa fa-times-circle-o'></i> Total Percentual Adicionado"
        );
        $("#label-total").addClass("has-error");

        $("#btnSalvarItemApropriacao").prop("disabled", true);
      } else if (data.status == "igual") {
        $("#div-total").addClass("has-success");
        $("#label-total").html(
          "<i class='fa fa-check'></i> Total Percentual Adicionado"
        );
        $("#label-total").addClass("has-success");

        $("#btnSalvarItemApropriacao").prop("disabled", false);
      } else {
        $("#div-total").addClass("has-warning");
        $("#label-total").html(
          "<i class='fa fa-bell-o'></i> Total Percentual Adicionado"
        );
        $("#label-total").addClass("has-warning");

        $("#btnSalvarItemApropriacao").prop("disabled", false);
      }

      $("#SGP_PercentualTotal").val(data.douValor);
      $("#SGP_Percentual").trigger("chosen:updated");
    }).fail(function (data) {
      $("#btnSalvarItemApropriacao").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarItemContratoApropriacao() {
  if ($.trim($("#CEN_ID").val()) == "") {
    $.notify("Centro de custo precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#PLF_Conta2").val()) == "") {
    $.notify("Plano Financeiro precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SGP_Percentual").val()) == "") {
    $.notify("Percentual da apropriação precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#checkOrcamentoObrigatorio").val()) == "S" &&
    $.trim($("#ORC_ID2").val()) == ""
  ) {
    $.notify("Orçamento precisa ser informado.", "warn");
  } else if (
    $.trim($("#checkOrcamentoObrigatorio").val()) == "S" &&
    $.trim($("#OCI_ID2").val()) == ""
  ) {
    $.notify("Item do Orçamento precisa ser informado.", "warn");
  } else {
    $("#btnSalvarItemApropriacao").prop("disabled", true);

    $.ajax({
      url: $.trim($("#contratos_apropriacoes_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        CIO_ID: $.trim($("#CIO_ID").val()),
        ICT_ID: $.trim($("#hddCodigo").val()),
        CEN_ID: $.trim($("#CEN_ID").val()),
        ORC_ID: $.trim($("#ORC_ID2").val()),
        OCI_ID: $.trim($("#OCI_ID2").val()),
        PLF_Conta: $.trim($("#PLF_Conta2").val()),
        CIO_Percentual: $.trim($("#SGP_Percentual").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarItemApropriacao").prop("disabled", false);

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SGP_Percentual").val("");
        $("#OCI_ID2").html("<option value=''>" + strSelecione + "</option>");
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").trigger("chosen:updated");
        $("#div-total").removeClass("has-success");
        $("#div-total").removeClass("has-warning");
        $("#div-total").removeClass("has-error");
        $("#label-total").removeClass("has-success");
        $("#label-total").removeClass("has-warning");
        $("#label-total").removeClass("has-error");
        $("#label-total").html("Total Percentual Adicionado");
        $("#SGP_PercentualTotal").val(data.total);

        if ($.trim($("#CIO_ID").val()) != "") {
          $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SGP_Percentual").prop(
            "disabled",
            true
          );
          $(
            "#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SGP_Percentual"
          ).trigger("chosen:updated");
        }

        consultarItemContratoApropriacoes();
      })
      .fail(function (data) {
        $("#btnSalvarItemApropriacao").prop("disabled", false);
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarMedicoes() {
  $(".btn-formulario").prop("disabled", true);
  var strLabel = $("#medicoes-consultar").html();
  $("#medicoes-consultar").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddMedicoesConsultar").val()),
    dataType: "json",
    cache: false,
    data: {
      CON_ID: $.trim($("#CON_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        $("#medicoes-consultar").html(strLabel);
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#medicoes-consultar").html(data.strHtml);

      $(".dropdown-submenu a.test").on("click", function (e) {
        $(this).next("ul").toggle();
        e.stopPropagation();
        e.preventDefault();
      });
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#medicoes-consultar").html(strLabel);

      dialogAlert(strAtencao, data.responseText, 6);
    });

  setTimeout(function () {
    $("#ENT_Medicao_ID").chosen();
  }, 500);
}

function salvarMedicoes() {
  if ($.trim($("#ENT_Medicao_ID").val()) == "") {
    $.notify("Fornecedor precisa ser informado.", "warn");
  } else {
    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnSalvarMedicoes").html();
    $("#btnSalvarMedicoes").html(strCarregando);
    preLoadingOpen();

    var strRetem = strNao;
    if ($("#MED_RetemCaucao").is(":checked")) {
      strRetem = strSim;
    }
   
    var checkedFaturamento = $("#MED_FaturamentoDireto")[0].checked;

    $.ajax({
      url: $.trim($("#hddMedicoesSalvar").val()),
      dataType: "json",
      cache: false,
      data: {
        MED_ID: $.trim($("#MED_ID").val()),
        CON_ID: $.trim($("#CON_ID").val()),
        ENT_ID: $.trim($("#ENT_Medicao_ID").val()),
        COP_ID: $.trim($("#COP_ID").val()),
        MED_DataReferencia: $.trim($("#MED_DataReferencia").val()),
        MED_Detalhes: $.trim($("#MED_Detalhes").val()),
        MED_RetemCaucao: strRetem,
        MED_PercentualCaucao: $.trim($("#MED_PercentualCaucao").val()),
        MED_FaturamentoDireto: checkedFaturamento,
        MED_EntFaturamento: $("#MED_EntFaturamento").val(),
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvarMedicoes").html(strLabel);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");
        $("#ENT_Medicao_ID, #MED_Detalhes, #MED_EntFaturamento").val("");
        $("#MED_FaturamentoDireto")[0].checked = false;
        $('#MED_EntFaturamento_div').collapse('hide');

        $("#ENT_Medicao_ID").trigger("chosen:updated");
        atualizarComprasMedicoes();
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvarMedicoes").html(strLabel);
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function editarMedicoes(strAction) {
  $.post(
    $.trim(strAction),
    function (data) {
      //alert(data);
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          $("#MED_ID").val(data.arrDados[0].MED_ID);
          $("#MED_Detalhes").val(data.arrDados[0].MED_Detalhes);
          $("#MED_DataReferencia").val(data.arrDados[0].MED_DataReferencia);
          $("#ENT_Medicao_ID").val(data.arrDados[0].ENT_ID);
          $("#ENT_Medicao_ID").trigger("chosen:updated");
          $("#COP_ID").val(data.arrDados[0].COP_ID);
          $("#MED_PercentualCaucao").val(data.arrDados[0].MED_PercentualCaucao);

          if(data.arrDados[0].MED_FaturamentoDireto == 1){

            $("#MED_FaturamentoDireto")[0].checked = true;
            $('#MED_EntFaturamento_div').collapse('show');
            $("#MED_EntFaturamento").val(data.arrDados[0].MED_EntFaturamento);
          }else{

            $("#MED_FaturamentoDireto")[0].checked = false;
            $('#MED_EntFaturamento_div').collapse('hide');
          }
        
      


          if (data.arrDados[0].MED_RetemCaucao == strSim) {
            $("#MED_RetemCaucao").bootstrapToggle("on");
          } else {
            $("#MED_RetemCaucao").bootstrapToggle("off");
          }
        } else {
          $.notify(data.mensagem, "error");
        }
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function adicionarGestoresUsuarios(usuarioID, cadastroID) {
  $.ajax({
    url: $.trim($("#hddGestoresSalvar").val()),
    dataType: "json",
    cache: false,
    data: {
      USU_ID: $.trim(usuarioID),
      CAX_ID: $.trim(cadastroID),
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $.notify(data.mensagem, "success");
    })
    .fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });

  /*$.post($.trim($('#hddGestoresSalvar').val()), {
    USU_ID: $.trim(usuarioID),
    CAX_ID: $.trim(cadastroID)
  },
  function(data){
    //alert(data);
    if(data.sucesso == 'true'){
      $.notify(data.mensagem, "success");
    }else{
      $.notify(data.mensagem, "error");
    }
    }, 'json'
  );*/
}

function limparItensMedicoes() {
  $(
    "#ICT_IDFiltro, #CON_NumeroFiltro, #CON_PesquisarFiltro, #MEI_Quantidade, #MEI_ValorUnitario, #MEI_ValorTotal, #MED_DataReferencia, #CON_PesquisarFiltroGrid, #ICT_QuantidadeSaldo, #MED_PercentualCaucao"
  ).val("");
  $("#MED_RetemCaucao").bootstrapToggle("off");
  $("#lbl-filtro-itens_contratos").html(strLabelFiltroPesquisarItens);
}

function salvarItemMedicao() {
  if ($.trim($("#ICT_IDFiltro").val()) == "") {
    $.notify("Item do contrato precisa ser informado.", "warn");
  } else if ($.trim($("#MEI_Quantidade").val()) == "") {
    $.notify("Quantidade precisa ser informada.", "warn");
  } else if ($.trim($("#MEI_ValorUnitario").val()) == "") {
    $.notify("Valor unitário precisa ser informada.", "warn");
  } else {
    $("#btnSalvarItemMedicao").prop("disabled", true);
    var strLabel = $("#btnSalvarItemMedicao").html();
    $("#btnSalvarItemMedicao").html(strCarregando);

    $.ajax({
      url: $.trim($.trim($("#hddMedicoesItensSalvar").val())),
      dataType: "json",
      cache: false,
      data: {
        RDB_Selecionado: $.trim(
          $("input[name='rdbTipoPesquisa']:checked").val()
        ),
        MED_ID: $.trim($("#hddCodigo").val()),
        ICT_ID: $.trim($("#ICT_IDFiltro").val()),
        MEI_Quantidade: $.trim($("#MEI_Quantidade").val()),
        MEI_ValorUnitario: $.trim($("#MEI_ValorUnitario").val()),
        MEI_ValorTotal: $.trim($("#MEI_ValorTotal").val()),
        MEI_Percentual_IPI: $.trim($("#MEI_Percentual_IPI").val()),
        MEI_Percentual_IIS: $.trim($("#MEI_Percentual_IIS").val()),
        MEI_Percentual_ICMS: $.trim($("#MEI_Percentual_ICMS").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarItemMedicao").prop("disabled", false);
        $("#btnSalvarItemMedicao").html(strLabel);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $("#divSaldoItemContrato").removeClass("has-error");
        $("#divSaldoItemContrato").removeClass("has-success");
        atualizarComprasMedicoes();
        consultarItensContratos($.trim($("#CON_ID2").val()));
      })
      .fail(function (data) {
        $("#btnSalvarItemMedicao").prop("disabled", false);
        $("#btnSalvarItemMedicao").html(strLabel);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function atualizarComprasMedicoes() {
  limparItensMedicoes();
  consultarItensMedicoes($.trim($("#CON_ID2").val()));
  consultarMedicoes();
}

function calcularItensMedicoesValores() {
  //$('#btnSalvarItemMedicao').prop('disabled', true);

  if ($("#rdbTipoPesquisa1").is(":checked")) {
    var strTipoPesquisa = 1;
  } else {
    var strTipoPesquisa = 2;
  }

  $.ajax({
    url: $.trim($("#hddMedicoesItensCalcular").val()),
    dataType: "json",
    cache: false,
    data: {
      MEI_Quantidade: $.trim($("#MEI_Quantidade").val()),
      MEI_ValorTotal: $.trim($("#MEI_ValorTotal").val()),
      MEI_ValorUnitario: $.trim($("#MEI_ValorUnitario").val()),
      MEI_Percentual_IPI: $.trim($("#MEI_Percentual_IPI").val()),
      MEI_Percentual_IIS: $.trim($("#MEI_Percentual_IIS").val()),
      MEI_Percentual_ICMS: $.trim($("#MEI_Percentual_ICMS").val()),
      MEI_TipoPesquisa: strTipoPesquisa,
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        //$('#divPesquisaRapida').html(data.error.msg);
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      if (strTipoPesquisa == 1) {
        $("#MEI_ValorTotal").val(data.total);
      } else {
        $("#MEI_Quantidade").val(data.total);
      }

      $("#btnSalvarItemMedicao").prop("disabled", false);
    })
    .fail(function (data) {
      //$('#divPesquisaRapida').html('');
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function consultarItensMedicoes(intCodigo) {
  $(".btn-formulario").prop("disabled", true);
  $("#divConsultarItensMedicioes").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddMedicoesItensConsultar").val()),
    dataType: "json",
    cache: false,
    data: {
      MED_ID: $.trim($("#hddCodigo").val()),
      CON_ID: $.trim(intCodigo),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        $("#divConsultarItensMedicioes").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#divConsultarItensMedicioes").html(data.strHtml);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#divConsultarItensMedicioes").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarDocumentos(e) {
  if (e.keyCode == 13) {
    if ($.trim($("#DOC_Numero3").val()) != "") {
      $("#txtDataInicial, #txtDataFinal").val("");
    }

    consultarDocumentos();
  }
}

function consultarDocumentos() {
  var strLabel = consultarPadraoInicial();
  var arrEmpresas = new Array();
  var arrFornecedores = new Array();
  var arrStatus = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='ENT_ID[]'] option:selected").each(function () {
    arrFornecedores.push($(this).val());
  });

  $("select[name='SGP_Status[]'] option:selected").each(function () {
    arrStatus.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#hddDocumentosConsultar").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: arrEmpresas,
      ENT_ID: arrFornecedores,
      SGP_Status: arrStatus,
      SGP_Codigo: $.trim($("#DOC_Numero3").val()),
      SGP_Numero: $.trim($("#CPG_Numero").val()),
      SGP_Pesquisar: $.trim($("#DOC_Pesquisar3").val()),
      DOC_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      DOC_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
      DOC_DataEmissaoInicial: $.trim($("#txtDataEmissaoInicial").val()),
      DOC_DataEmissaoFinal: $.trim($("#txtDataEmissaoFinal").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);
      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarItensDocumentos(documentoID) {
  $("#itens-dados").html(strCarregando);

  $.post(
    $.trim($("#hddDocumentosItensConsultar").val()),
    {
      DOC_ID: $.trim(documentoID),
      EMP_ID: $.trim($("#EMP_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#itens-dados").html(data.strHtml);
        $("#spnTotalRegistros").html(data.totalRegistros);
        $(".maskMoney").maskMoney({
          showSymbol: false,
          symbol: "R$",
          decimal: ",",
          thousands: ".",
        });
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function novoItemDocumentos() {
  $.post(
    $.trim($("#hddDocumentosItensNovo").val()),
    {
      DOC_ID: $.trim($("#DOC_ID").val()),
      EMP_ID: $.trim($("#EMP_ID").val()),
      EMP_Info: $.trim($("#EMP_Dados").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          setInitFunctions();
        }, 500);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function removerItensDocumentos() {
  var arrSelecionados = new Array();

  $("input[type=checkbox][name='items[]']:checked").each(function () {
    arrSelecionados.push($(this).val());
  });

  if (arrSelecionados.length > 0) {
    if (confirm("Confirma a exclusão dos itens selecionados ?")) {
      $.post(
        $.trim($("#hddDocumentosItensExcluir").val()),
        {
          DOC_ID: $.trim($("#DOC_ID").val()),
          DOI_ID: arrSelecionados,
        },
        function (data) {
          //alert(data); return;
          if (data.sucesso == "true") {
            $.notify(data.mensagem, "success");

            consultarItensDocumentos($("#DOC_ID").val());
          } else {
            $.notify(data.mensagem, "error");
          }
        },
        "json"
      );
    }
  } else {
    $.notify("Selecione no minímo um item para exclusão", "error");
    return;
  }
}

function atualizarItensDocumentos(itemID, douValor, strFlag) {
  $.ajax({
    url: $.trim($("#hddDocumentosItensEditar").val()),
    dataType: "json",
    cache: false,
    data: {
      DOC_ID: $.trim($("#DOC_ID").val()),
      ITE_ID: $.trim(itemID),
      douValor: douValor,
      strFlag: strFlag,
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $.notify(data.mensagem, "success");
      consultarItensDocumentos($.trim($("#DOC_ID").val()));
    })
    .fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarItemDocumento() {
  if ($.trim($("#PDI_ID").val()) == "" && $.trim($("#MEI_ID").val()) == "") {
    $.notify("Item do pedido ou da medição precisa ser selecionado.", "warn");
  } else if ($.trim($("#DOI_Quantidade").val()) == "") {
    $.notify("Quantidade precisa ser informada.", "warn");
  } else {
    $("#btnSalvarItemDocumento").prop("disabled", true);
    var strLabel = $("#btnSalvarItemDocumento").html();
    $("#btnSalvarItemDocumento").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddDocumentosItensSalvar").val()),
      dataType: "json",
      cache: false,
      data: {
        DOC_ID: $.trim($("#DOC_ID").val()),
        PDI_ID: $.trim($("#PDI_ID").val()),
        MEI_ID: $.trim($("#MEI_ID").val()),
        DOI_Quantidade: $.trim($("#DOI_Quantidade").val()),
        DOI_Percentual_IPI: $.trim($("#DOI_Percentual_IPI").val()),
        DOI_Percentual_IIS: $.trim($("#DOI_Percentual_IIS").val()),
        DOI_Percentual_ICMS: $.trim($("#DOI_Percentual_ICMS").val()),
        DOI_PercentualCaucao: $.trim($("#DOI_PercentualCaucao").val()),
        DOC_ValorFrete: $.trim($("#DOI_ValorFrete2").val()),
        DOC_ValorDesc: $.trim($("#DOI_ValorDesconto2").val()),
      },
      type: "POST",
    })
      .success(function (data) {
     
        $("#btnSalvarItemDocumento").html(strLabel);
        $("#btnSalvarItemDocumento").prop("disabled", false);

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");
        $("#DOI_Quantidade").val("");
        $("#DOI_ValorUnitario").val("");
        $("#DOI_ValorTotal").val("");
        $("#DOI_Percentual_IPI").val("");
        $("#DOI_Percentual_IIS").val("");
        $("#DOI_Percentual_ICMS").val("");

        if ($.trim($("#PDI_ID").val()) != "") {
          $("#btnCancelarItensDocumentosPedido").trigger("click");
        } else {
          $("#btnCancelarItensDocumentosMedicao").trigger("click");
        }

        $("#PDI_ID").val("");
        $("#MEI_ID").val("");
        $("#DOC_ValorFrete").val(data.douValorFrete);
        $("#DOC_ValorDesconto").val(data.douValorDesc);

        consultarItensDocumentos($.trim($("#DOC_ID").val()));
      })
      .fail(function (data) {
        $("#btnSalvarItemDocumento").html(strLabel);
        $("#btnSalvarItemDocumento").prop("disabled", false);
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function cancelarFiltroDocumentoItensPedidos(valor) {
  $("#btnFiltrarItensDocumentosPedidos").show();
  $("#btnFiltrarItensDocumentosMedicoes").show();
  $("#divFiltroDocumentoItensPedidos").show();
  $("#divFiltroDocumentoItensMedicoes").show();
  $("#lbl-item-documento-pedido").html(valor);
  $("#divPercentualCaucao").hide();
  $(
    "#PDI_ID, #MEI_ID, #DOI_Quantidade, #DOI_PercentualCaucao, #DOI_ValorUnitario, #DOI_ValorTotal, #DOI_Percentual_IPI, #DOI_Percentual_IIS, #DOI_Percentual_ICMS"
  ).val("");
}

function cancelarFiltroDocumentoItensMedicoes(valor) {
  $("#btnFiltrarItensDocumentosPedidos").show();
  $("#btnFiltrarItensDocumentosMedicoes").show();
  $("#divFiltroDocumentoItensPedidos").show();
  $("#divFiltroDocumentoItensMedicoes").show();
  $("#lbl-item-documento-medicao").html(valor);
  $("#divPercentualCaucao").hide();
  $(
    "#PDI_ID, #MEI_ID, #DOI_Quantidade, #DOI_PercentualCaucao, #DOI_ValorUnitario, #DOI_ValorTotal, #DOI_Percentual_IPI, #DOI_Percentual_IIS, #DOI_Percentual_ICMS"
  ).val("");
}

function calcularItemDocumento() {
  if (
    textoParaFloat($.trim($("#DOI_Quantidade").val())) == 0 ||
    textoParaFloat($.trim($("#DOI_ValorUnitario").val())) == 0
  ) {
    $.notify(
      "Quantidade e valor unitário precisa ser informada ou ser maior que zero.",
      "warn"
    );
  } else {
    $("#btnSalvarItemDocumento").prop("disabled", true);

    $.post(
      $.trim($("#hddDocumentosItemCalcular").val()),
      {
        DOI_Quantidade: $.trim($("#DOI_Quantidade").val()),
        DOI_ValorUnitario: $.trim($("#DOI_ValorUnitario").val()),
        DOI_Percentual_IPI: $.trim($("#DOI_Percentual_IPI").val()),
        DOI_Percentual_IIS: $.trim($("#DOI_Percentual_IIS").val()),
        DOI_Percentual_ICMS: $.trim($("#DOI_Percentual_ICMS").val()),
        DOI_PercentualCaucao: $.trim($("#DOI_PercentualCaucao").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#DOI_ValorTotal").val(data.douValorTotal);
          $("#btnSalvarItemDocumento").prop("disabled", false);
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  }
}

function editarSolicitacao() {
  $.post(
    $.trim($("#hddEditarSolicitacoes2").val()),
    {
      SOL_ID: $.trim($("#SOL_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divEmpresas").html(data.selectEmpresa);
        $("#SOL_Observacoes").prop("disabled", false);
        $("#CAX_Obra_ID").prop("disabled", false);
        $('#CAX_Obra_ID').selectpicker('refresh');

        $("#EMP_ID").val(data.EMP_ID);
        $("#EMP_Codigo").val(data.EMP_Codigo);
        $("#EMP_Pesquisar").val(data.EMP_RazaoSocial);

        $("#btnEditar").hide();
        $("#btnSalvar2").show();
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarSolicitacao() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else {
    $.post(
      $.trim($("#hddSalvarSolicitacoes2").val()),
      {
        SOL_ID: $.trim($("#SOL_ID").val()),
        EMP_ID: $.trim($("#EMP_ID").val()),
        SOL_Observacoes: $.trim($("#SOL_Observacoes").val()),
        CAX_Obra_ID: $.trim($("#CAX_Obra_ID").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");

          setTimeout(function () {
            redir("");
          }, 1000);
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  }
}

function editarPedido() {
  $.post(
    $.trim($("#hddPedidosEditar2").val()),
    {
      PED_ID: $.trim($("#PED_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divEmpresas").html(data.selectEmpresas);
        $("#divEntidades").html(data.selectEntidades);

        $("#PED_Observacoes").prop("disabled", false);

        $("#EMP_ID").val(data.EMP_ID);
        $("#EMP_Codigo").val(data.EMP_Codigo);
        $("#EMP_Pesquisar").val(data.EMP_RazaoSocial);

        $("#ENT_ID").val(data.ENT_ID);
        $("#ENT_Codigo").val(data.ENT_Codigo);
        $("#ENT_Pesquisar").val(data.ENT_RazaoSocial);

        $("#btnEditar").hide();
        $("#btnSalvar2").show();
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function editarItemApropriacaoPedido(id) {
  $(".btn-editar").prop("disabled", true);

  $.ajax({
    url:
      $.trim($("#itens_pedidos_apropriacoes_editar").val()) + "/" + $.trim(id),
    dataType: "json",
    cache: false,
    data: {
      SOA_ID: id,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-editar").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#PIA_ID").val(data.arrDados[0].PIA_ID);
      $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").prop("disabled", false);
      $("#CEN_ID").val(data.arrDados[0].CEN_ID);
      $("#ORC_ID2").val(data.arrDados[0].ORC_ID);
      $("#PLF_Conta2").val(data.arrDados[0].PLF_Conta);
      $("#SGP_Percentual").val(data.arrDados[0].PIA_Percentual);
      $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").trigger("chosen:updated");
      $("#ORC_ID2").trigger("change");

      setTimeout(function () {
        $("#OCI_ID2").val(data.arrDados[0].OCI_ID);
        $("#OCI_ID2").trigger("chosen:updated");
      }, 1000);

      $("#SGP_Percentual").prop("disabled", true);
      $("#CEN_ID").focus();
    })
    .fail(function (data) {
      $(".btn-editar").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarPedido() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ENT_ID").val()) == "") {
    $.notify("Fornecedor precisa ser informado.", "warn");
    return;
  } else {
    $.post(
      $.trim($("#hddPedidosSalvar").val()),
      {
        PED_ID: $.trim($("#PED_ID").val()),
        EMP_ID: $.trim($("#EMP_ID").val()),
        ENT_ID: $.trim($("#ENT_ID").val()),
        PED_Observacoes: $.trim($("#PED_Observacoes").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");

          setTimeout(function () {
            redir("");
          }, 1000);
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  }
}

function enterPesquisarContasPagar(e) {
  if (e.keyCode == 13) {
    if ($.trim($("#CPG_Numero3").val()) != "") {
      $("input[type='date']").val('');
    }

    consultarContasPagar();
  }
}

function consultarContasPagar() {
  var strLabel = consultarPadraoInicial();
  var arrEmpresas = new Array();
  var arrFornecedores = new Array();
  var arrCadastroAuxiliares = new Array();
  var arrAprovados = new Array();
  var arrStatus = new Array();
  var arrCentroCustos = new Array();
  var arrPlanosFinanceiros = new Array();
  var arrOrigens = new Array();
  var arrTemImpostos = new Array();
  var arrFaturamentoDireto = new Array();
  var arrCadastradoPor = new Array();
  var arrAtualizadoPor = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='ENT_ID[]'] option:selected").each(function () {
    arrFornecedores.push($(this).val());
  });

  $("select[name='CAX_ID[]'] option:selected").each(function () {
    arrCadastroAuxiliares.push($(this).val());
  });

  $("select[name='SEL_SimNao[]'] option:selected").each(function () {
    arrAprovados.push($(this).val());
  });

  $("select[name='CPP_Status[]'] option:selected").each(function () {
    arrStatus.push($(this).val());
  });

  $("select[name='PLF_Conta[]'] option:selected").each(function () {
    arrPlanosFinanceiros.push($(this).val());
  });

  $("select[name='CEN_ID[]'] option:selected").each(function () {
    arrCentroCustos.push($(this).val());
  });

  $("select[name='SGP_Origem[]'] option:selected").each(function () {
    arrOrigens.push($(this).val());
  });

  $("select[name='SGP_TemImposto[]'] option:selected").each(function () {
    arrTemImpostos.push($(this).val());
  });

  $("select[name='SGP_FaturamentoDireto[]'] option:selected").each(function () {
    arrFaturamentoDireto.push($(this).val());
  });

  $("select[name='USU_Cadastro_ID[]'] option:selected").each(function () {
    arrCadastradoPor.push($(this).val());
  });

  $("select[name='USU_Atualiza_ID[]'] option:selected").each(function () {
    arrAtualizadoPor.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#hddContasPagarConsultar").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      EMP_ID: arrEmpresas,
      ENT_ID: arrFornecedores,
      CAX_ID: arrCadastroAuxiliares,
      CEN_ID: arrCentroCustos,
      PLF_Conta: arrPlanosFinanceiros,
      CPG_Numero: $.trim($("#CPG_Numero3").val()),
      CPG_Pesquisar: $.trim($("#CPG_Pesquisar3").val()),
      CPG_DataCadastroInicial: $("#txtDataInicial").val(),
      CPG_DataCadastroFinal: $("#txtDataFinal").val(),
      CPP_DataVencimentoInicial: $("#txtDataVencimentoInicial").val(),
      CPP_DataVencimentoFinal: $("#txtDataVencimentoFinal").val(),
      CPG_DataEmissaoInicial: $.trim($("#txtDataEmissaoInicial").val()),
      CPG_DataEmissaoFinal: $.trim($("#txtDataEmissaoFinal").val()),
      USU_Cadastro_ID: arrCadastradoPor,
      USU_Atualizacao_ID: arrAtualizadoPor,
      CPP_Status: arrStatus,
      CPG_Aprovado: arrAprovados,
      CPG_Origem: arrOrigens,
      SGP_TemImposto: arrTemImpostos,
      SGP_FaturamentoDireto: arrFaturamentoDireto
    },
  }).success(function (data) {
      consultarPadraoSucesso(strLabel);

      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#consultar-dados").html(data.strHtml);
      $("#pagination").html(data.pagination);
      consultarPadraoSucessoPaginacao(data);

  }).fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function visualizarMedicao(medicaoID) {
  $("#consultar-dados").html(strCarregando);

  $.post(
    $.trim($("#hddItensMedicoesVisualizar").val()),
    {
      MED_ID: $.trim(medicaoID),
      CON_ID: $.trim($("#CON_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        //$('#consultar-dados').html(data.strHtml);
        dialogAlert(data.strTitulo, data.strHtml, 3);
        return;
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function exibirMaisInformacoesItensCotacoes() {
  preLoadingOpen();
  if (
    $(".exibirEsconder").css("display") == "none" ||
    $(".exibirEsconder").css("visibility") == "hidden"
  ) {
    $(".exibirEsconder").show();
    $("#maisMenos")
      .removeClass("glyphicon glyphicon-plus")
      .addClass("glyphicon glyphicon-minus");
  } else {
    $(".exibirEsconder").hide();
    $("#maisMenos")
      .removeClass("glyphicon glyphicon-minus")
      .addClass("glyphicon glyphicon-plus");
  }

  preLoadingClose();
}

function editarDocumento() {
  $(".btn-formulario").prop("disabled", true);
  var strLabel = $("#btnEditar").html();
  $("#btnEditar").html(strCarregando);

  preLoadingOpen();

  $.ajax({
    url: $.trim($("#documentos_editar2").val()),
    dataType: "json",
    cache: false,
    data: {
      DOC_ID: $.trim($("#DOC_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnEditar").html(strLabel);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $(
        "#CAX_ID, #DOC_DataEmissao, #DOC_ValorFrete, #DOC_ValorDesconto, #DOC_ValorAcrescimo, #DOC_Numero"
      ).prop("disabled", false);
      $("#COP_ID").prop("disabled", data.COP_ID);

      $("#btnEditar").hide();
      $("#btnSalvar2").show();
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnEditar").html(strLabel);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarDocumento() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ENT_ID").val()) == "") {
    $.notify("Fornecedor precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#DOC_Numero").val()) == "") {
    $.notify("Número do documento precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CAX_ID").val()) == "") {
    $.notify("Tipo do documento precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#DOC_DataEmissao").val()) == "") {
    $.notify("Data da emissão precisa ser informada.", "warn");
    return;
  } else {
    $.post(
      $.trim($("#hddDocumentosSalvar").val()),
      {
        DOC_ID: $.trim($("#DOC_ID").val()),
        EMP_ID: $.trim($("#EMP_ID").val()),
        ENT_ID: $.trim($("#ENT_ID").val()),
        DOC_Numero: $.trim($("#DOC_Numero").val()),
        CAX_ID: $.trim($("#CAX_ID").val()),
        DOC_DataEmissao: $.trim($("#DOC_DataEmissao").val()),
        DOC_ValorFrete: $.trim($("#DOC_ValorFrete").val()),
        DOC_ValorDesconto: $.trim($("#DOC_ValorDesconto").val()),
        DOC_ValorAcrescimo: $.trim($("#DOC_ValorAcrescimo").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");

          setTimeout(function () {
            redir("");
          }, 1000);
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  }
}

function salvarAnexoContrato() {
  if ($.trim($("#COA_Descricao").val()) == "") {
    $.notify("Descrição do anexo precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#COA_Anexo").val()) == "") {
    $.notify("Anexo precisa ser informado.", "warn");
    return;
  } else {
    $("#frmAnexos").submit();
  }
}

function editarContrato() {
  $("#CON_Observacoes").prop("disabled", false);
  $("#btnEditar").hide();
  $("#btnSalvar2").show();
}

function salvarContrato() {
  $("#frmFormulario").submit();
}

function enterPesquisarCondicoesTabelas(e) {
  if (e.keyCode == 13) {
    consultarCondicoesTabelas();
  }
}

function consultarCondicoesTabelas() {
  $(".btn-filtro").prop("disabled", true);
  $("#spnTotalRegistrosConsultar").show();
  $(".btn-impressao").hide();
  var strLabel = $("#btnFiltrar").html();
  $("#btnFiltrar, #consultar-dados").html(strCarregando);
  $("#spnTotalRegistrosConsultar").html(strCarregandoIcone);
  preLoadingOpen();

  var arrTipoAmortizacoes = new Array();
  var arrStatus = new Array();

  $("#CON_TipoAmortizacao")
    .find("option:selected")
    .each(function () {
      arrTipoAmortizacoes.push($(this).val());
    });

  $("select[name='SGP_Status[]'] option:selected").each(function () {
    arrStatus.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#hddCondicoesTabelasConsultar").val()),
    dataType: "json",
    cache: false,
    data: {
      CON_Numero: $.trim($("#CON_Numero").val()),
      CON_Descricao: $.trim($("#CON_Descricao").val()),
      CON_TipoAmortizacao: arrTipoAmortizacoes,
      CON_DataCadastroInicial: $("#txtDataInicial").val(),
      CON_DataCadastroFinal: $("#txtDataFinal").val(),
      CON_Status: arrStatus,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#btnFiltrar").html(strLabel);
      preLoadingClose();

      if (data.error) {
        $("#spnTotalRegistrosConsultar, #consultar-dados").html("");
        $("#spnTotalRegistrosConsultar").hide();

        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#consultar-dados").html(data.strHtml);
      $("#pagination").html(data.pagination);
      $("#pagination").on("click", "a", function (e) {
        e.preventDefault();
        var pageno = $(this).attr("data-ci-pagination-page");
        loadPagination(data.url, pageno, data.arrFiltros);
      });

      if (data.totalRegistros > 0) {
        $(".btn-impressao").show();
      }

      $("#spnTotalRegistrosConsultar").html(data.totalRegistros);
    })
    .fail(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#consultar-dados").html("");
      $(".btn-impressao").hide();
      $("#btnFiltrar").html(strLabel);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function verificaBtnTabelaVendasComercial() {
  if ($.trim($("#CON_ID").val()) == "") {
    $("#btnSalvar").prop("disabled", true);

    var strCorRecebimento = $("#total-serie").css("color");
    if (strCorRecebimento == "rgb(50, 205, 50)") {
      var intI = 0;
      $("input[type=text][name='CSE_QuantidadeParcelas[]']").each(function () {
        if ($(this).val() == 0) {
          intI++;

          $.notify("Quantidade de parcela precisa ser maior que zero.", "warn");
          return;
        }
      });

      if (intI == 0) {
        $("#btnSalvar").prop("disabled", false);
      }

      return true;
    }
  }

  return false;
}

function salvarTabelasVendas() {
  var arrEstruturas = new Array();
  $("select[name='EST_ID[]'] option:selected").each(function () {
    if ($.trim($(this).val()) != "") {
      arrEstruturas.push($(this).val());
    }
  });

  if ($.trim($("#CON_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CON_TipoAmortizacao").val()) == "") {
    $.notify("Tipo de Amortização precisa ser informada.", "warn");
    return;
  } else if (arrEstruturas.length == 0) {
    $.notify("Deve ser selecionado no minímo 1 (UMA) estrutura.", "warn");
    return;
  } else {
    //Verifica as informações da Condição de Recebimentos
    var arrPeriodicidades = new Array();
    var arrMultiplicadores = new Array();
    var arrCorrecoes = new Array();
    var arrJuros = new Array();
    var arrFormas = new Array();
    var arrMesInicio = new Array();
    var arrParcelas = new Array();
    var arrPercentuais = new Array();

    $("select[name='CSE_Periodicidade[]'] option:selected").each(function () {
      if ($.trim($(this).val()) != "") {
        arrPeriodicidades.push($(this).text());
        arrMultiplicadores.push($(this).val());
      }
    });

    $("select[name='SEL_SimNao2[]'] option:selected").each(function () {
      if ($.trim($(this).val()) != "") {
        arrCorrecoes.push($(this).val());
      }
    });

    $("select[name='SEL_SimNao3[]'] option:selected").each(function () {
      if ($.trim($(this).val()) != "") {
        arrJuros.push($(this).val());
      }
    });

    $("select[name='CSE_FormaPagamento[]'] option:selected").each(function () {
      if ($.trim($(this).val()) != "") {
        arrFormas.push($(this).val());
      }
    });

    $("input[type=text][name='CSE_PeriodoInicio[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrMesInicio.push($(this).val());
      }
    });

    $("input[type=text][name='CSE_QuantidadeParcelas[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrParcelas.push($(this).val());
      }
    });

    $("input[type=text][name='CSE_PercentualSerie[]']").each(function () {
      arrPercentuais.push($(this).val());
    });

    //Valores OK
    if (
      arrPeriodicidades.length == arrMultiplicadores.length &&
      arrCorrecoes.length == arrJuros.length &&
      arrFormas.length == arrMesInicio.length &&
      arrParcelas.length == arrPeriodicidades.length
    ) {
      $(".btn-formulario").prop("disabled", true);
      var strLabel = $("#btnSalvar").html();
      $("#btnSalvar").html(strCarregando);
      preLoadingOpen();

      var strExibirPortalVendas = strNao;
      if ($("#CON_ExibirPortalVendas").is(":checked")) {
        strExibirPortalVendas = strSim;
      }

      $.ajax({
        url: $.trim($("#condicoes_salvar").val()),
        dataType: "json",
        cache: false,
        data: {
          CON_ID: $.trim($("#CON_ID").val()),
          CON_Descricao: $.trim($("#CON_Descricao").val()),
          CON_TipoAmortizacao: $.trim($("#CON_TipoAmortizacao").val()),
          CON_TaxaCapitalizacao: $.trim($("#CON_TaxaCapitalizacao").val()),
          CON_TaxaDescapitalizacao: $.trim(
            $("#CON_TaxaDescapitalizacao").val()
          ),
          arrEstruturas: arrEstruturas,
          arrPeriodicidades: arrPeriodicidades,
          arrMultiplicadores: arrMultiplicadores,
          arrCorrecoes: arrCorrecoes,
          arrJuros: arrJuros,
          arrFormas: arrFormas,
          arrMesInicio: arrMesInicio,
          arrParcelas: arrParcelas,
          arrPercentuais: arrPercentuais,
          CON_ExibirPortalVendas: strExibirPortalVendas,
        },
        type: "POST",
      })
        .success(function (data) {
          preLoadingClose();
          $(".btn-formulario").prop("disabled", false);
          $("#btnSalvar").html(strLabel);

          if (data.error) {
            dialogAlert(strInformacao, data.error.msg, 6);
            return;
          }

          $.notify(data.mensagem, "success");

          setTimeout(function () {
            redir(data.redir);
          }, 1000);
        })
        .fail(function (data) {
          $(".btn-formulario").prop("disabled", false);
          $("#btnSalvar").html(strLabel);
          preLoadingClose();

          dialogAlert(strAtencao, data.responseText, 6);
        });
    } else {
      $.notify(
        "Verifique se todos os campos de parcelas e períodos estão preenchidos corretamente.",
        "error"
      );
      return;
    }
  }
}

function consultarTabelasVendasComercial() {
  preLoadingOpen();
  $("#consultar-dados").html(strCarregando);

  $.post(
    $.trim($("#hddTabelasVendasComercialDetalhes").val()),
    {
      EST_ID: $.trim($("#EST_ID").val()),
      EST_ObservacaoTabela: $.trim($("#EST_ObservacaoTabela").val()),
      EST_PrecoMedio: $.trim($("#EST_PrecoMedio").val()),
      CON_Calculo: $.trim($("#CON_Calculo").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#consultar-dados").html(data.strHtml);
        $("#spnValorTotal").html(data.douTotal);
        $("#spnAreaPrivativaTotal").html(data.douAreaTotal);
        $("#spnPrecoMedioPonderado").html(data.douPrecoMedioPonderado);

        setInitFunctions();

        requireDataTables(false, false, false, true, true, false, true, false);

        setTimeout(function () {
          $("select[name='cntConsulta_length']").val(200);
          $("select[name='cntConsulta_length']").trigger("change");
          $("select[name='cntConsulta_length']").hide();
          $("#cntConsulta_length").hide();
          preLoadingClose();
        }, 500);
      } else {
        $.notify(data.mensagem, "error");
        preLoadingClose();
      }

      return;
    },
    "json"
  );
}

function atualizarUnidadeCoeficiente(
  unidadeID,
  valorCoeficiente,
  areaPrivativa,
  valorPrecoID,
  valorPrecoFinalID
) {
  if (
    $.trim($("#hddExecutar").val()) != $.trim(valorCoeficiente) &&
    $.trim(valorCoeficiente) != ""
  ) {
    $("#" + valorPrecoFinalID).html(strCarregando);
    $("#spnValorTotal").html(strCarregando);
    $("#spnPrecoMedioPonderado").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddTabelasVendasComercialCoeficiente").val()),
      dataType: "json",
      cache: false,
      type: "POST",
      data: {
        EST_ID: $.trim($("#EST_ID").val()),
        arrUnidades: $.trim(unidadeID),
        CON_Calculo: $.trim($("#CON_Calculo").val()),
        UNI_PercentualCoeficiente: $.trim(valorCoeficiente),
        UNI_PrecoM2Unidade: $("#" + valorPrecoID).val(),
        EST_PrecoMedio: $("#EST_PrecoMedio").val(),
        UNI_AreaPrivida: $.trim(areaPrivativa),
      },
    })
      .success(function (data) {
        //alert(data); return;
        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $("#" + valorPrecoID).val(data.douResultado);
        $("#" + valorPrecoFinalID).val(data.douCalculo);
        $("#spnValorTotal").html(data.douTotal);
        $("#spnPrecoMedioPonderado").html(data.douPrecoMedioPonderado);

        $.notify(data.mensagem, "success");
      })
      .fail(function (data) {
        //alert(data); return;
        $("#" + valorPrecoID).val("");
        $("#" + valorPrecoFinalID).val("");
        $("#spnValorTotal").html("");
        $("#spnPrecoMedioPonderado").html("");

        dialogAlert(strAtencao, data.responseText, 6);
        return;
      });
  }
}

function atualizarUnidadePreco(
  unidadeID,
  valorM2,
  areaPrivativa,
  htmlTotal,
  valorFloreira
) {
  if (
    $.trim($("#hddExecutar").val()) != $.trim(valorM2) &&
    $.trim(valorM2) != ""
  ) {
    $(".btn-formulario").prop("disabled", true);
    $("#htmlTotal, #spnValorTotal, #spnPrecoMedioPonderado").html(
      strCarregando
    );

    $.ajax({
      url: $.trim($("#condicoes_tabelas_vendas_preco_unidade").val()),
      dataType: "json",
      cache: false,
      data: {
        EST_ID: $.trim($("#EST_ID").val()),
        arrUnidades: $.trim(unidadeID),
        CON_Calculo: $.trim($("#CON_Calculo").val()),
        EST_PrecoMedio: $.trim($("#EST_PrecoMedio").val()),
        UNI_PrecoM2Unidade: $.trim(valorM2),
        UNI_AreaPrivida: areaPrivativa,
        UNI_AreaPrivativaFloreiras: $.trim(valorFloreira),
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);

        if (data.error) {
          $("#htmlTotal, #spnValorTotal, #spnPrecoMedioPonderado").html("");
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $("#" + htmlTotal).val(data.douPreco);
        $("#spnValorTotal").html(data.douTotal);
        $("#spnPrecoMedioPonderado").html(data.douPrecoMedioPonderado);

        $.notify(data.mensagem, "success");
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#htmlTotal, #spnValorTotal, #spnPrecoMedioPonderado").html("");

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}


function atualizarUnidadePrecoFinal(
  unidadeID,
  valorFinal,
  areaPrivativa,
  htmlTotal,
  valorFloreira
) {
  if (
    $.trim($("#hddExecutar").val()) != $.trim(valorFinal) &&
    $.trim(valorFinal) != ""
  ) {
    $(".btn-formulario").prop("disabled", true);
    $("#htmlTotal, #spnValorTotal, #spnPrecoMedioPonderado").html(
      strCarregando
    );

    $.ajax({
      url: $.trim($("#condicoes_tabelas_vendas_preco_unidade").val()),
      dataType: "json",
      cache: false,
      data: {
        EST_ID: $.trim($("#EST_ID").val()),
        arrUnidades: $.trim(unidadeID),
        CON_Calculo: $.trim($("#CON_Calculo").val()),
        EST_PrecoMedio: $.trim($("#EST_PrecoMedio").val()),
        UNI_PrecoUnidade: $.trim(valorFinal),
        UNI_AreaPrivida: areaPrivativa,
        UNI_AreaPrivativaFloreiras: $.trim(valorFloreira),
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);

        if (data.error) {
          $("#htmlTotal, #spnValorTotal, #spnPrecoMedioPonderado").html("");
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $("#" + htmlTotal).val(data.douPrecoM2);        
        $("#spnValorTotal").html(data.douTotal);
        $("#spnPrecoMedioPonderado").html(data.douPrecoMedioPonderado);

        $.notify(data.mensagem, "success");
      })        
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#htmlTotal, #spnValorTotal, #spnPrecoMedioPonderado").html("");

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function salvarTabelasVendasComercial() {
  if (
    $.trim($("#EST_PrecoMedio").val()) == 0 ||
    $.trim($("#EST_PrecoMedio").val()) == ""
  ) {
    $.notify("Preço médio precisa ser maior que zero.", "warn");
    return;
  } else {
    if (confirm("Confirma a altereção dos valores para todas as unidades ?")) {
      $(".btn-formulario").prop("disabled", true);
      var strLabel = $("#btnFluxo2").html();
      $("#btnFluxo2").html(strCarregando);
      preLoadingOpen();

      $.ajax({
        url: $.trim($("#hddTabelasVendasComercialCoeficiente").val()),
        dataType: "json",
        cache: false,
        type: "POST",
        data: {
          EST_ID: $.trim($("#EST_ID").val()),
          CON_Calculo: $.trim($("#CON_Calculo").val()),
          EST_PrecoMedio: $.trim($("#EST_PrecoMedio").val()),
        },
      })
        .success(function (data) {
          $(".btn-formulario").prop("disabled", false);
          $("#btnFluxo2").html(strLabel);
          preLoadingClose();

          if (data.error) {
            dialogAlert(strAtencao, data.error.msg, 6);
            return;
          }

          $.notify(data.mensagem, "success");
          consultarTabelasVendasComercial();
        })
        .fail(function (data) {
          $(".btn-formulario").prop("disabled", false);
          $("#btnFluxo2").html(strLabel);
          preLoadingClose();

          dialogAlert(strAtencao, data.responseText, 6);
        });
    }
  }
}

function salvarUnidadesProdutos() {
  if ($.trim($("#PRU_TipoUnidade").val()) == "") {
    $.notify("Tipo da unidade precisa ser informada.", "warn");
    return;
  } else {
    $.post(
      $.trim($("#hddProdutosUnidadesSalvar").val()),
      {
        PRD_ID: $.trim($("#PRD_ID").val()),
        PRU_ID: $.trim($("#PRU_ID").val()),
        PRU_TipoUnidade: $.trim($("#PRU_TipoUnidade").val()),
        PRU_QuantidadeUnidades: $.trim($("#PRU_QuantidadeUnidades").val()),
        PRU_AreaPrivada: $.trim($("#PRU_AreaPrivada").val()),
        PRU_AreaTotal: $.trim($("#PRU_AreaTotal").val()),
        PRU_QuantidadeDormitorios: $.trim(
          $("#PRU_QuantidadeDormitorios").val()
        ),
        PRU_QuantidadeSuites: $.trim($("#PRU_QuantidadeSuites").val()),
        PRU_QuantidadeBanheiros: $.trim($("#PRU_QuantidadeBanheiros").val()),
        PRU_QuantidadeVagas: $.trim($("#PRU_QuantidadeVagas").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          limparUnidadesProdutos();
          consultarUnidadesProdutos();

          $.notify(data.mensagem, "success");
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  }
}

function consultarUnidadesProdutos() {
  $("#dados-unidades").html(strCarregando);

  $.post(
    $.trim($("#hddProdutosUnidadesConsultar").val()),
    {
      GRE_ID: $.trim($("#GRE_ID").val()),
      PRD_ID: $.trim($("#PRD_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#dados-unidades").html(data.strHtml);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function limparUnidadesProdutos() {
  $("#PRU_ID").val("");
  $("#PRU_TipoUnidade").val("");
  $("#PRU_QuantidadeUnidades").val("");
  $("#PRU_QuantidadeEstoque").val("");
  $("#PRU_ValorUnidade").val("");
  $("#PRU_ValorM2Unidade").val("");
  $("#PRU_AreaPrivada").val("");
  $("#PRU_AreaTotal").val("");
  $("#PRU_QuantidadeDormitorios").val("");
  $("#PRU_QuantidadeSuites").val("");
  $("#PRU_QuantidadeBanheiros").val("");
  $("#PRU_QuantidadeVagas").val("");
}

function editarUnidadeProduto(id) {
  limparUnidadesProdutos();

  $.post(
    $.trim($("#hddProdutosUnidadesEditar").val()),
    {
      PRD_ID: $.trim($("#PRD_ID").val()),
      PRU_ID: $.trim(id),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#PRU_ID").val(data.arrDados[0].PRU_ID);
        $("#PRU_TipoUnidade").val(data.arrDados[0].PRU_TipoUnidade);
        $("#PRU_QuantidadeUnidades").val(
          data.arrDados[0].PRU_QuantidadeUnidades
        );
        $("#PRU_QuantidadeEstoque").val(data.arrDados[0].PRU_QuantidadeEstoque);
        $("#PRU_ValorUnidade").val(data.arrDados[0].PRU_ValorUnidade);
        $("#PRU_ValorM2Unidade").val(data.arrDados[0].PRU_ValorM2Unidade);
        $("#PRU_AreaPrivada").val(data.arrDados[0].PRU_AreaPrivada);
        $("#PRU_AreaTotal").val(data.arrDados[0].PRU_AreaTotal);
        $("#PRU_QuantidadeDormitorios").val(
          data.arrDados[0].PRU_QuantidadeDormitorios
        );
        $("#PRU_QuantidadeSuites").val(data.arrDados[0].PRU_QuantidadeSuites);
        $("#PRU_QuantidadeBanheiros").val(
          data.arrDados[0].PRU_QuantidadeBanheiros
        );
        $("#PRU_QuantidadeVagas").val(data.arrDados[0].PRU_QuantidadeVagas);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarTabelasProdutos() {
  if ($.trim($("#PTA_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#PTA_MesAno").val()) == "") {
    $.notify("Mês/Ano precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#PTA_Anexo").val()) == "") {
    $.notify("Anexo precisa ser informado.", "warn");
    return;
  } else {
    $("#frmFormularioAnexo").submit();
  }
}

function consultarTabelasProdutos() {
  $("#dados-tabelas").html(strCarregando);

  $.post(
    $.trim($("#hddProdutosTabelasConsultar").val()),
    {
      GRE_ID: $.trim($("#GRE_ID").val()),
      PRD_ID: $.trim($("#PRD_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#dados-tabelas").html(data.strHtml);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function atualizarUnidadeProduto(id, strTipoUnidade) {
  $("#hddCodigoSelecionado").val(id);

  $.post(
    $.trim($("#hddProdutosUnidadesAtualizar").val()),
    {
      PRD_ID: $.trim($("#PRD_ID").val()),
      PRU_ID: $.trim(id),
      strTipoUnidade: strTipoUnidade,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          $("#btnAtualizarUnidadeEstoque").prop("disabled", true);
          $(".maskMoney").maskMoney({
            showSymbol: false,
            symbol: "R$",
            decimal: ",",
            thousands: ".",
          });
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function atualizarInfoUnidadeProduto() {
  if ($.trim($("#PRU_QuantidadeEstoque2").val()) == "") {
    $.notify("Quantidade de estoque precisa ser informada.", "warn");
    return;
    PRU_QuantidadeEstoque;
  } else if (
    parseInt($("#PRU_QuantidadeEstoque2").val()) >
    parseInt($("#PRU_QuantidadeUnidades2").val())
  ) {
    $.notify(
      "Quantidade de estoque deve ser menor que quantidade de unidades.",
      "warn"
    );
    return;
  } else if ($.trim($("#PRU_ValorUnidade2").val()) == "") {
    $.notify("Valor do estoque precisa ser informado.", "warn");
    return;
  } else {
    $.post(
      $.trim($("#hddProdutosUnidadesAtualizar2").val()),
      {
        PRD_ID: $.trim($("#PRD_ID").val()),
        PRU_ID: $.trim($("#hddCodigoSelecionado").val()),
        PRU_QuantidadeEstoque: $.trim($("#PRU_QuantidadeEstoque2").val()),
        PRU_ValorUnidade: $.trim($("#PRU_ValorUnidade2").val()),
        PRU_ValorM2Unidade: $.trim($("#PRU_ValorM2Unidade2").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");
          consultarUnidadesProdutos();
          $("#btnCloseDialogAlert").trigger("click");
        } else {
          $.notify(data.mensagem, "error");
        }

        $("#hddCodigoSelecionado").val("");
        $("#PRU_QuantidadeEstoque").val("");
        $("#PRU_ValorUnidade").val("");
        $("#PRU_ValorM2Unidade").val("");
      },
      "json"
    );
  }
}

function calcularValorUnidadeProduto(valorUnidade, areaPrivada) {
  $("#PRU_ValorM2Unidade2").val("");
  $("#btnAtualizarUnidadeEstoque").prop("disabled", true);

  $.post(
    $.trim($("#hddProdutosUnidadesCalcular").val()),
    {
      PUH_ValorUnidade: valorUnidade,
      PRU_AreaPrivada: areaPrivada,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#PRU_ValorM2Unidade2").val(data.douValorCalculado);

        if (data.douValorCalculadoSemMascara > 0) {
          $("#btnAtualizarUnidadeEstoque").prop("disabled", false);
        }
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function confirmarProdutoConferido() {
  $(document).ready(function () {
    $.post(
      $.trim($("#hddProdutoConfirmarConferir").val()),
      {
        PRD_ID: $.trim($("#PRD_ID").val()),
        PRD_Descricao: $.trim($("#PRD_Descricao").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#confirm-padrao-titulo").html(data.label_confirmar);
          $("#confirm-padrao-descricao").html(data.descricao_confirmar);

          $("#linkConfirmarPadrao").one("click", function (e) {
            $("#linkConfirmarPadrao").prop("disabled", true);
            conferirProduto();
          });
        } else {
          $("#confirm-padrao").modal("toggle");
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  });
}

function conferirProduto() {
  $.post(
    $.trim($("#hddProdutoConferido").val()),
    {
      PRD_ID: $.trim($("#PRD_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        redir("", "parent");
      } else {
        $("#confirm-padrao").modal("toggle");
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function confirmarProdutoUnidadeConferido(unidadeID, strTipoUnidade) {
  $("#hddCodigoSelecionado").val(unidadeID);

  $(document).ready(function () {
    $.post(
      $.trim($("#hddProdutosUnidadesConfirmarConferir").val()),
      {
        PRD_ID: $.trim($("#PRD_ID").val()),
        PRU_ID: $.trim(unidadeID),
        PRU_TipoUnidade: $.trim(strTipoUnidade),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#confirm-padrao-titulo").html(data.label_confirmar);
          $("#confirm-padrao-descricao").html(data.descricao_confirmar);

          $("#linkConfirmarPadrao").one("click", function (e) {
            $("#linkConfirmarPadrao").prop("disabled", true);
            conferirProdutoUnidade();
          });
        } else {
          $("#confirm-padrao").modal("toggle");
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  });
}

function conferirProdutoUnidade() {
  $.post(
    $.trim($("#hddProdutosUnidadesConferir").val()),
    {
      PRD_ID: $.trim($("#PRD_ID").val()),
      PRU_ID: $("#hddCodigoSelecionado").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#linkConfirmarPadrao").prop("disabled", false);
        $.notify(data.mensagem, "success");

        $("#confirm-padrao").modal("toggle");
        consultarUnidadesProdutos();
      } else {
        $("#confirm-padrao").modal("toggle");
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function confirmarProdutoTabelaConferido(tabelaID, tabelaDescricao) {
  $("#hddCodigoSelecionado").val(tabelaID);

  $(document).ready(function () {
    $.post(
      $.trim($("#hddProdutosTabelasConfirmarConferir").val()),
      {
        PRD_ID: $.trim($("#PRD_ID").val()),
        PTA_ID: $.trim(tabelaID),
        PTA_Descricao: $.trim(tabelaDescricao),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#confirm-padrao-titulo").html(data.label_confirmar);
          $("#confirm-padrao-descricao").html(data.descricao_confirmar);

          $("#linkConfirmarPadrao").one("click", function (e) {
            $("#linkConfirmarPadrao").prop("disabled", true);
            conferirProdutoTabela();
          });
        } else {
          $("#confirm-padrao").modal("toggle");
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  });
}

function conferirProdutoTabela() {
  $.post(
    $.trim($("#hddProdutosTabelasConferir").val()),
    {
      PRD_ID: $.trim($("#PRD_ID").val()),
      PTA_ID: $("#hddCodigoSelecionado").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#linkConfirmarPadrao").prop("disabled", false);
        $.notify(data.mensagem, "success");

        $("#confirm-padrao").modal("toggle");
        consultarTabelasProdutos();
      } else {
        $("#confirm-padrao").modal("toggle");
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function confirmarExclusaoSerie(linhaID, condicaoID, serieID, strMensagem) {
  if (confirm(strMensagem + "\nConfirma a exclusão dos itens selecionados ?")) {
    $.post(
      $.trim($("#hddCondicoesSerieExcluir").val()),
      {
        CON_ID: $.trim(condicaoID),
        CSE_ID: $.trim(serieID),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");
          $("#" + linhaID).hide();
          $("#" + linhaID).html("");
          $("#linha").show();

          $(".calcularPercentualComercial").trigger("blur");
        } else {
          $.notify(data.mensagem, "error");
        }
      },
      "json"
    );
  }
}

//
function editarObservacoesTabelaVendas() {
  $.post(
    $.trim($("#hddCondicoesTabelasEditarObservacoes").val()),
    {
      EST_ID: $.trim($("#EST_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(strInformacao, data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarObservacoesTabelaVendas() {
  $.post(
    $.trim($("#hddCondicoesTabelasSalvarObservacoes").val()),
    {
      EST_ID: $.trim($("#EST_ID").val()),
      EST_ObservacaoTabela: $.trim($("#EST_ObservacaoTabela").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#btnCloseDialogAlert").trigger("click");
        $.notify(data.mensagem, "success");
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function confirmarEspelhoVendasProposta(unidadeID, unidadeInfo) {
  $("#hddCodigoSelecionado").val("");

  $.post(
    $.trim($("#hddEspelhoVendasConfirmar").val()),
    {
      EST_ID: $.trim($("#EST_ID").val()),
      BLO_ID: $.trim($("#BLO_ID").val()),
      UNI_ID: $.trim(unidadeID),
      UNI_Info: unidadeInfo,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $(document).ready(function () {
          $("#confirm-padrao-titulo").html(data.label_confirmar);
          $("#confirm-padrao-descricao").html(unidadeInfo);
          $("#linkConfirmarPadraoReservada").hide();
          $("#linkConfirmarPadrao").html("Proposta");

          $("#linkConfirmarPadrao").one("click", function (e) {
            $("#hddCodigoSelecionado").val(unidadeID);
            $("#linkConfirmarPadrao").prop("disabled", true);

            espelhoVendasGerarProposta();
          });
        });
      } else {
        $("#confirm-padrao").modal("toggle");
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function espelhoVendasGerarProposta() {
  $.post(
    $.trim($("#hddEspelhoVendasProposta").val()),
    {
      EST_ID: $.trim($("#EST_ID").val()),
      BLO_ID: $.trim($("#BLO_ID").val()),
      UNI_ID: $.trim($("#hddCodigoSelecionado").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        redir(data.redir);
      } else {
        $.notify(data.mensagem, "error");
      }

      $("#linkConfirmarPadrao").prop("disabled", false);
      $("#confirm-padrao").modal("toggle");
    },
    "json"
  );
}

function carregarCondicoesUnidades(unidadeID, condicaoSelecionada) {
  $("#CON_ID").selectpicker({
    liveSearch: true,
  });

  $("#CON_ID").html("<option value=''>" + strSelecione + "</option>");
  $("#CON_ID").selectpicker("refresh");

  $.ajax({
    url: $.trim($("#hddCarregarCondicoesUnidades").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      GRE_ID: $.trim($("#GRE_ID").val()),
      UNI_ID: unidadeID,
      CON_ExibirPortalVendas: strSim,
      SGP_Valor: true,
      PRO_ID: $.trim($("#PRO_ID").val()),
    },
  })
    .success(function (data) {
      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      var strHtml = "";

      if (data.arrDados != undefined) {
        var selected = "";
        for (var i = 0; i < data.arrDados.length; i++) {
          selected = "";
          if (data.arrDados[i].CON_ID == condicaoSelecionada)
            selected = "selected";

          strHtml +=
            "<option " +
            selected +
            " value='" +
            data.arrDados[i].CON_ID +
            "'>" +
            data.arrDados[i].CON_Descricao +
            "</option>";
        }
      }

      $("#CON_ID").append(strHtml);
      $("#CON_ID").selectpicker("refresh");

      if ($.trim($("#CON_ID").val()) != "") {
        $("#CON_ID").trigger("change");
      }
    })
    .fail(function (data) {
      $("#CON_ID").html("<option value=''>" + strSelecione + "</option>");
      $("#CON_ID").selectpicker("refresh");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function carregarCondicoesUnidadesMultiplos(
  arrUnidades,
  arrGruposEmpresas = null
) {
  $("#CON_ID").multiselect("destroy");
  $("#CON_ID").html("");

  $.ajax({
    url: $.trim($("#hddCarregarCondicoesUnidades").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      GRE_ID: arrGruposEmpresas,
      UNI_ID: arrUnidades,
      SGP_Valor: true,
    },
  })
    .success(function (data) {
      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      var strHtml = "";
      if (data.arrDados.length > 0) {
        for (var i = 0; i < data.arrDados.length; i++) {
          strHtml +=
            "<option value='" +
            data.arrDados[i].CON_ID +
            "'>" +
            data.arrDados[i].CON_Descricao +
            "</option>";
        }
      }

      $("#CON_ID").append(strHtml);
      $("#CON_ID").multiselect("refresh");
    })
    .fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });

  /*$.post($.trim($('#hddCarregarCondicoesUnidades').val()), {
    UNI_ID: unidadeID,
    valor: 1
  },
  function(data){
    //alert(data); return;
    if (data.sucesso == 'true'){
      var strHtml = "";
      if (data.arrDados.length > 0){
        for (var i=0; i<data.arrDados.length; i++){
          strHtml+= "<option value='"+data.arrDados[i].CON_ID+"'>"+data.arrDados[i].CON_Descricao+"</option>";
        }
      }

      $('#CON_ID').append(strHtml);
      $('#CON_ID').multiselect('refresh');
    }else{
      $.notify(data.mensagem, "error");
    }

    $('.multiplos').multiselect('refresh');
    }, 'json'
  );*/
}

function bootstrapConfirm(strTitulo, strMensagem) {
  BootstrapDialog.confirm({
    title: strTitulo,
    message: strMensagem,
    type: BootstrapDialog.TYPE_PRIMARY,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-primary",
    callback: function (result) {
      if (result) {
        alert("Yup.");
      }
    },
  });
}

function criarLinhaSeriesParcelas(htmlID) {
  var strHtml = $("#" + htmlID).html();

  $.ajax({
    url: $.trim($("#hddPropostaGerarLinha").val()),
    dataType: "json",
    cache: false,
    data: {
      htmlID: htmlID,
      teste: true,
      strHtml: strHtml,
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#tblSeriesParcelas").append(data.strHtml);
      $(data.antigoID_esconder).hide();
      $(data.removerID_esconder).show();

      setInitFunctions();
      $(".calcularValorParcelaProposta").trigger("blur");
    })
    .fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function bootstrapConfirmLinhaSeriesParcelas(
  unidadeID,
  condicaoID,
  periodoID,
  strTitulo,
  strMensagem,
  htmlID
) {
  BootstrapDialog.confirm({
    title: strTitulo,
    message: strMensagem,
    type: BootstrapDialog.TYPE_PRIMARY,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-primary",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.ajax({
          url: $.trim($("#hddPropostasCondicoesExcluir").val()),
          dataType: "json",
          cache: false,
          type: "POST",
          data: {
            UNI_ID: unidadeID,
            CSE_ID: condicaoID,
            CTB_Periodo: periodoID,
          },
        })
          .success(function (data) {
            preLoadingClose();

            if (data.error) {
              dialogAlert(strAtencao, data.error.msg, 6);
              return;
            }

            $("#" + htmlID).html("");
            $("#" + htmlID).hide();
            $.notify(data.mensagem, "success");
          })
          .fail(function (data) {
            preLoadingClose();
            dialogAlert(strAtencao, data.responseText, 6);
          });

        /*$.post($.trim($('#hddPropostasCondicoesExcluir').val()), {
          UNI_ID: unidadeID,
          CSE_ID: condicaoID,
          CTB_Periodo: periodoID
        },
        function(data){
          //alert(data); return;
          if (data.sucesso == 'true'){
            $('#'+htmlID).html('');
            $('#'+htmlID).hide();
            $.notify(data.mensagem, "success");
          }else{
            $.notify(data.mensagem, "error");
          }
          preLoadingClose();
          }, 'json'
        );*/
      }
    },
  });
}

function bootstrapConfirmLinhaSeriesParcelas2(
  serieID,
  strTitulo,
  strMensagem,
  htmlID
) {
  BootstrapDialog.confirm({
    title: strTitulo,
    message: strMensagem,
    type: BootstrapDialog.TYPE_PRIMARY,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-primary btnDialogAlert",
    callback: function (result) {
      if (result) {
        $(".btn-default, .btnDialogAlert").prop("disabled", true);
        var strLabel = $(".btnDialogAlert").html();
        $(".btnDialogAlert").html(strCarregando);

        $.ajax({
          url: $.trim($("#hddPropostasCondicoesExcluir2").val()),
          dataType: "json",
          cache: false,
          data: {
            PRO_ID: $.trim($("#PRO_ID").val()),
            PCS_ID: $.trim(serieID),
          },
          type: "POST",
        })
          .success(function (data) {
            $(".btnDialogAlert").html(strLabel);
            $(".btn-default, .btnDialogAlert").prop("disabled", true);

            if (data.error) {
              dialogAlert(strInformacao, data.error.msg, 6);
              return;
            }

            $("#" + htmlID).html("");
            $("#" + htmlID).hide();
            $.notify(data.mensagem, "success");

            setTimeout(function () {
              redir("", "parent");
            }, 1500);
          })
          .fail(function (data) {
            $(".btnDialogAlert").html(strLabel);
            $(".btn-default, .btnDialogAlert").prop("disabled", true);

            dialogAlert(strAtencao, data.responseText, 6);
          });
      }
    },
  });
}

function checarExisteEmailCorretor() {
  $.post(
    $.trim($("#hddComercialCorretorChecarEmail").val()),
    {
      COR_Email: $.trim($("#COR_Email").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.totalRegistros > 0) {
          $("#COR_ID").val(data.COR_ID);
        }
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function carregarImobiliariaCorretor(corretorID, htmlImobiliariaID) {
  $("#" + htmlImobiliariaID).html(strCarregando);

  $.post(
    $.trim($("#hddComercialCorretorID").val()),
    {
      GRE_ID: $.trim($("#GRE_ID").val()),
      COR_ID: $.trim(corretorID),
    },
    function (data) {
      //alert(data); return;
      var contadorLinha = htmlImobiliariaID.split("_");
      var PRC_PercentualComissao = "PRC_PercentualComissao_" + contadorLinha[1];
      var PRC_TipoCalculo = "PRC_TipoCalculo_" + contadorLinha[1];
      var PRC_ValorComissao = "PRC_ValorComissao_" + contadorLinha[1];

      if (data.sucesso == "true") {
        $("#" + htmlImobiliariaID).html(data.IMO_Descricao);

        $("#" + PRC_TipoCalculo).val($('option:contains("Percentual")').val());
        calcularComercialPropostaTipoComissa(
          PRC_TipoCalculo,
          PRC_PercentualComissao,
          PRC_ValorComissao
        );

        $("#" + PRC_PercentualComissao).val(data.CGE_PercentualCorretor);
        calcularComissaoProposta(
          PRC_TipoCalculo,
          PRC_ValorComissao,
          PRC_PercentualComissao
        );
      } else {
        $.notify(data.mensagem, "error");
        return;
      }
    },
    "json"
  );
}

function criarLinhaEquipeVendas(htmlID) {
  $.post(
    $.trim($("#hddCorretorGerarLinha").val()),
    {
      strHtml: $("#" + htmlID).html(),
      htmlID: htmlID,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#tblEquipeVendas").append(data.strHtml);
        $(data.antigoID_esconder).hide();
        $(data.removerID_esconder).show();
        $("#" + data.imobiliariaID).html("");

        setInitFunctions();

        $(".calcularComissaoProposta").blur(function (e) {
          var arrValores = new Array();
          var arrComissoes = new Array();
          var arrTipoCalculo = new Array();
          var arrValoresComissoes = new Array();
          var douValorTotal = $("#tdValorTotalProposta").html();

          $("select[name='PRC_TipoCalculo[]']").each(function () {
            arrTipoCalculo.push($(this).val());
          });

          $("input[type=text][name='PRC_PercentualComissao[]']").each(
            function () {
              arrValores.push($(this).val());
            }
          );

          $("input[type=text][name='PRC_ValorComissao[]']").each(function () {
            arrValoresComissoes.push($(this).val());
          });

          $.post(
            $.trim($("#hddPropostasCalcularComissoes").val()),
            {
              strTipoCalculo: arrTipoCalculo,
              douValorComissao: arrValoresComissoes,
              douValor: arrValores,
              douValorTotal: douValorTotal,
            },
            function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                for (var i = 0; i < arrTipoCalculo.length; i++) {
                  var strCampo = "PRC_PercentualComissao";
                  if (arrTipoCalculo[i] == "P") {
                    strCampo = "PRC_ValorComissao";
                  }

                  $("input[type='text'][name='" + strCampo + "[]']").each(
                    function () {
                      if ($.trim($(this).val()) == "") {
                        $(this).val(data.arrValoresCalculado[i]);
                      }
                    }
                  );
                }
              }
            },
            "json"
          );
        });
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function criarLinhaVagas(htmlID) {
  $.post(
    $.trim($("#propostas_vagas_gerar_linha").val()),
    {
      strHtml: $("#" + htmlID).html(),
      htmlID: htmlID,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#tblVagas").append(data.strHtml);
        $(data.antigoID_esconder).hide();
        $(data.removerID_esconder).show();

        $('[data-toggle="tooltip"]').tooltip({ html: true });
        $(".maskMoney").maskMoney({
          showSymbol: false,
          symbol: "R$",
          decimal: ",",
          thousands: ".",
          allowZero: true,
          defaultZero: false,
        });
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function bootstrapConfirmLinhaEquipeVendas(strTitulo, strMensagem, htmlID) {
  BootstrapDialog.confirm({
    title: strTitulo,
    message: strMensagem,
    type: BootstrapDialog.TYPE_PRIMARY,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-primary",
    callback: function (result) {
      if (result) {
        $("#" + htmlID).html("");
        $("#" + htmlID).hide();
      }
    },
  });
}

function salvarProposta() {
  if ($.trim($("#GRE_ID").val()) == "") {
    $.notify("Grupo de Empresa precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#UNI_ID").val()) == "") {
    $.notify("Unidade da proposta precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CON_ID").val()) == "") {
    $.notify("Condição da proposta precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CLI_ID").val()) == "") {
    $.notify("Cliente da proposta precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#PRO_DataProposta").val()) == "") {
    $.notify("Data da proposta precisa ser informada.", "warn");
    return;
  } else {
    $("#btnSalvar").prop("disabled", true);
    var strLabel = $("#btnSalvar").html();
    $("#btnSalvar").html(strCarregando);
    preLoadingOpen();

    var arrCorrecoes = new Array();
    var arrJuros = new Array();
    var arrPeriodicidades = new Array();
    var arrMultiplicador = new Array();
    var arrFormas = new Array();
    var arrPeriodos = new Array();
    var arrParcelas = new Array();
    var arrValores = new Array();
    var arrCorretores = new Array();
    var arrTiposCalculos = new Array();
    var arrComissoes = new Array();
    var arrComissoesValor = new Array();
    var arrDeduz = new Array();
    var arrArquivos = new Array();
    var arrTiposAnexos = new Array();
    var arrUnidades = new Array();
    var arrValoresVagas = new Array();
    var arrCodigos = new Array();
    var arrClientes = new Array();
    var form_data = new FormData();
    var i = 1;

    $("input[name='PRC_ID[]']").each(function () {
      arrCodigos.push($(this).val());
    });

    $("select[name='UNI_ID2[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrUnidades.push($(this).val());
      }
    });

    $("input[name='PRV_ValorVaga[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrValoresVagas.push($(this).val());
      }
    });

    $("input[name='arquivos[]']").each(function () {
      if ($.trim($(this).prop("files")[0]) != "") {
        arrArquivos.push($(this).prop("files")[0]);
        form_data.append("file_" + i, $(this).prop("files")[0]);
      }

      i++;
    });

    $("select[name='CAX_ID2[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrTiposAnexos.push($(this).val());
      }
    });

    if (arrTiposAnexos.length > 0 || arrArquivos.length > 0) {
      if (arrTiposAnexos.length != arrArquivos.length) {
        preLoadingClose();
        $("#btnSalvar").prop("disabled", false);
        $.notify(
          "Verifique se todos os campos da documentação do cliente foram informados.",
          "warn"
        );
        return;
      }
    }

    $("select[name='SEL_SimNao2[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrCorrecoes.push($(this).val());
      }
    });

    $("select[name='SEL_SimNao3[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrJuros.push($(this).val());
      }
    });

    $("select[name='CSE_Periodicidade[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrMultiplicador.push($(this).val());
      }
    });

    $("select[name='CSE_Periodicidade[]'] option:selected").each(function () {
      if ($.trim($(this).text()) == strSelecione) {
        arrPeriodicidades.push("");
      } else {
        arrPeriodicidades.push($(this).text());
      }
    });

    $("select[name='CSE_FormaPagamento[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrFormas.push($(this).val());
      }
    });

    $("input[type='date'][name='PCS_DataInicio[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrPeriodos.push($(this).val());
      }
    });

    $("input[name='PCS_QuantidadeParcelas[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrParcelas.push($(this).val());
      }
    });

    $("input[name='PCS_ValorParcela[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrValores.push($(this).val());
      }
    });

    $("select[name='COR_ID[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrCorretores.push($(this).val());
      }
    });

    $("select[name='PRC_TipoCalculo[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrTiposCalculos.push($(this).val());
      }
    });

    $("input[name='PRC_PercentualComissao[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrComissoes.push($(this).val());
      }
    });

    $("input[name='PRC_ValorComissao[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrComissoesValor.push($(this).val());
      }
    });

    $("select[name='SEL_SimNao4[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrDeduz.push($(this).val());
      }
    });

    //Entidades Secundários
    $("input[name='CLI_Multiplos_ID[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrClientes.push($(this).val());
      }
    });

    form_data.append("GRE_ID", $.trim($("#GRE_ID").val()));
    form_data.append("PRO_ID", $.trim($("#PRO_ID").val()));
    form_data.append("EST_ID", $.trim($("#EST_ID").val()));
    form_data.append("BLO_ID", $.trim($("#BLO_ID").val()));
    form_data.append("UNI_ID", $.trim($("#UNI_ID").val()));
    form_data.append("CON_ID", $.trim($("#CON_ID").val()));
    form_data.append("CLI_ID", $.trim($("#CLI_ID").val()));
    form_data.append("CAX_ID", $.trim($("#CAX_ID").val()));
    form_data.append(
      "CON_TipoAmortizacao",
      $.trim($("#CON_TipoAmortizacao").html())
    );
    form_data.append("PRO_DataProposta", $.trim($("#PRO_DataProposta").val()));
    form_data.append(
      "PRO_ValorProposta",
      $.trim($("#tdValorTotalProposta").html())
    );
    form_data.append("PRO_Observacoes", $.trim($("#PRO_Observacoes").val()));

    for (var i = 0; i < arrClientes.length; i++) {
      form_data.append("CLI_Multiplos_ID[]", arrClientes[i]);
    }

    for (var i = 0; i < arrCodigos.length; i++) {
      form_data.append("PRC_ID[]", arrCodigos[i]);
    }

    for (var i = 0; i < arrCorrecoes.length; i++) {
      form_data.append("arrCorrecoes[]", arrCorrecoes[i]);
    }

    for (var i = 0; i < arrJuros.length; i++) {
      form_data.append("arrJuros[]", arrJuros[i]);
    }

    for (var i = 0; i < arrMultiplicador.length; i++) {
      form_data.append("arrMultiplicador[]", arrMultiplicador[i]);
    }

    for (var i = 0; i < arrPeriodicidades.length; i++) {
      form_data.append("arrPeriodicidades[]", arrPeriodicidades[i]);
    }

    for (var i = 0; i < arrFormas.length; i++) {
      form_data.append("arrFormas[]", arrFormas[i]);
    }

    for (var i = 0; i < arrPeriodos.length; i++) {
      form_data.append("arrPeriodos[]", arrPeriodos[i]);
    }

    for (var i = 0; i < arrParcelas.length; i++) {
      form_data.append("arrParcelas[]", arrParcelas[i]);
    }

    for (var i = 0; i < arrValores.length; i++) {
      form_data.append("arrValores[]", arrValores[i]);
    }

    for (var i = 0; i < arrCorretores.length; i++) {
      form_data.append("arrCorretores[]", arrCorretores[i]);
    }

    for (var i = 0; i < arrTiposCalculos.length; i++) {
      form_data.append("arrTiposCalculos[]", arrTiposCalculos[i]);
    }

    for (var i = 0; i < arrComissoes.length; i++) {
      form_data.append("arrComissoes[]", arrComissoes[i]);
    }

    for (var i = 0; i < arrComissoesValor.length; i++) {
      form_data.append("arrComissoesValor[]", arrComissoesValor[i]);
    }

    for (var i = 0; i < arrDeduz.length; i++) {
      form_data.append("arrDeduz[]", arrDeduz[i]);
    }

    for (var i = 0; i < arrTiposAnexos.length; i++) {
      form_data.append("arrTiposAnexos[]", arrTiposAnexos[i]);
    }

    for (var i = 0; i < arrUnidades.length; i++) {
      form_data.append("arrUnidades[]", arrUnidades[i]);
    }

    for (var i = 0; i < arrValoresVagas.length; i++) {
      form_data.append("arrValoresVagas[]", arrValoresVagas[i]);
    }

    $.ajax({
      url: $.trim($("#hddPropostaSalvar").val()),
      cache: false,
      contentType: false,
      processData: false,
      dataType: "json",
      data: form_data,
      type: "POST",
      method: "POST",
      success: function (data) {
        $("#btnSalvar").prop("disabled", false);
        $("#btnSalvar").html(strLabel);
        preLoadingClose();

        if (data.sucesso == "false") {
          $.notify(data.mensagem, "warn");
          return;
        }

        if (data.error) {
          $(
            "#thTotalComissoesPercentualProposta, #thTotalComissoesValorProposta"
          ).html("");
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        setTimeout(function () {
          redir(data.redir, "parent");
        }, 1000);
      },
    }).fail(function (data) {
      $("#btnSalvar").prop("disabled", false);
      $("#btnSalvar").html(strLabel);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
  }
}

function marcarDesmarcarTerrenoObservacoes(codigo) {
  preLoadingOpen();
  $.post(
    $.trim($("#hddAtualizarVisualizarObservacoes").val()),
    {
      TOB_ID: $.trim(codigo),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $.notify(data.mensagem, "success");
        consultarTerrenosObservacoes();
        preLoadingClose();
        return;
      } else {
        $.notify(data.mensagem, "error");
        preLoadingClose();
        return;
      }
    },
    "json"
  );
}

function vincularEstudoTerreno(terrenoID) {
  $.post(
    $.trim($("#hddTerrenosEstudosConsultar2").val()),
    {
      TER_ID: $.trim(terrenoID),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(strInformacao, data.strHtml, 3);

        setTimeout(function () {
          requireDataTablesDialog(true);
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function vincularEstudoNovoTerreno(terrenoID) {
  $.post(
    $.trim($("#hddTerrenosEstudosNovoConsultar").val()),
    {
      TER_ID: $.trim(terrenoID),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(strInformacao, data.strHtml, 3);

        setTimeout(function () {
          requireDataTablesDialog(true);
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function vincularViabilidadeTerreno(terrenoID) {
  //preLoadingOpen();
  $.post(
    $.trim($("#hddViabilidadesTerrenosConsultar2").val()),
    {
      TER_ID: $.trim(terrenoID),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(strInformacao, data.strHtml, 3);

        setTimeout(function () {
          requireDataTablesDialog(true);
        }, 1000);
        //preLoadingClose();
      } else {
        $.notify(data.mensagem, "error");
        //preLoadingClose();
      }
    },
    "json"
  );
}

function vincularDesvincularViabilidadeTerreno(terrenoID, viabilidadeID) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddViabilidadesTerrenosAtualizar").val()),
    {
      TER_ID: $.trim(terrenoID),
      VIA_ID: $.trim(viabilidadeID),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        consultarTerrenosViabilidades();
        $.notify(data.mensagem, "success");
        preLoadingClose();
        return;
      } else {
        $.notify(data.mensagem, "error");
        preLoadingClose();
        return;
      }
    },
    "json"
  );
}

function getArraySelected(selectNameArray) {
  var arrDados = new Array();
  $("select[name='" + selectNameArray + "'] option:selected").each(function () {
    arrDados.push($(this).val());
  });

  return arrDados;
}

function vincularDesvincularEstudoTerreno(terrenoID, estudoID) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddTerrenosEstudosAtualizar").val()),
    {
      TER_ID: $.trim(terrenoID),
      EST_ID: $.trim(estudoID),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        consultarTerrenosEstudos();
        $.notify(data.mensagem, "success");
        preLoadingClose();
        return;
      } else {
        $.notify(data.mensagem, "error");
        preLoadingClose();
        return;
      }
    },
    "json"
  );
}

function vincularDesvincularEstudoNovoTerreno(terrenoID, estudoID) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddTerrenosEstudosNovoAtualizar").val()),
    {
      TER_ID: $.trim(terrenoID),
      ETV_ID: $.trim(estudoID),
    },
    function (data) {
      // alert(data); return;
      if (data.sucesso == "true") {
        consultarTerrenosEstudoNovo();
        $.notify(data.mensagem, "success");
        preLoadingClose();
        return;
      } else {
        $.notify(data.mensagem, "error");
        preLoadingClose();
        return;
      }
    },
    "json"
  );
}

function enterPesquisarPropostasPortalVendas(e) {
  $("#txtDataInicial, #txtDataFinal").val("");
  if (e.keyCode == 13) {
    consultarPropostasVendas();
  }
}

function aprovarProposta(propostaID, strMensagem) {
  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem,
    size: BootstrapDialog.SIZE_WIDE,
    type: BootstrapDialog.TYPE_SUCCESS,
    id: "dialogConfirmBootstrap",
    buttons: [
      {
        label: strLabelNao,
        cssClass: "btn-formulario",
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: "btn-success btn-formulario",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        id: "btnConfirmPadraoYes",
        action: function () {
          $(".btn-formulario").prop("disabled", true);
          var strLabel = $("#btnConfirmPadraoYes").html();
          $("#btnConfirmPadraoYes").html(strCarregando);

          $.ajax({
            url: $.trim($("#hddPropostasAprovar").val()),
            dataType: "json",
            cache: false,
            data: {
              PRO_ID: propostaID,
            },
            type: "POST",
          })
            .success(function (data) {
              $(".btn-formulario").prop("disabled", false);
              $("#btnConfirmPadraoYes").html(strLabel);

              if (data.error) {
                dialogAlert(strAtencao, data.error.msg, 6);
                return;
              }

              $("#dialogConfirmBootstrap").modal("hide");
              $.notify(data.mensagem, "success");

              if ($.trim($("#PRO_ID").val()) != "") {
                setTimeout(function () {
                  redir("", "parent");
                }, 1500);
              } else {
                consultarPropostas();
              }
            })
            .fail(function (data) {
              $("button").prop("disabled", false);
              $("#btnConfirmPadraoYes").html(strLabel);
              dialogAlert(strAtencao, data.responseText, 6);
              preLoadingClose();
            });
        },
      },
    ],
  });
}

function reprovarProposta(propostaID, strMensagem) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_DANGER,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-danger",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddPropostasReprovar").val()),
          {
            PRO_ID: propostaID,
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              $.notify(data.mensagem, "success");
            } else {
              $.notify(data.mensagem, "error");
            }

            if ($.trim($("#PRO_ID").val()) != "") {
              setTimeout(function () {
                redir("", "parent");
              }, 1500);
            } else {
              consultarPropostas();
            }

            preLoadingClose();
            return;
          },
          "json"
        );
      }
    },
  });
}

function aprovarCreditoProposta(propostaID, strMensagem) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_SUCCESS,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-success",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddPropostasAprovarCredito").val()),
          {
            PRO_ID: propostaID,
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              $.notify(data.mensagem, "success");
            } else {
              $.notify(data.mensagem, "error");
            }

            if ($.trim($("#PRO_ID").val()) != "") {
              setTimeout(function () {
                redir("", "parent");
              }, 1500);
            } else {
              consultarPropostas();
            }

            preLoadingClose();
            return;
          },
          "json"
        );
      }
    },
  });
}

function reprovarCreditoProposta(propostaID, strMensagem) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_DANGER,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-danger",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddPropostasReprovarCredito").val()),
          {
            PRO_ID: propostaID,
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              $.notify(data.mensagem, "success");
            } else {
              $.notify(data.mensagem, "error");
            }

            if ($.trim($("#PRO_ID").val()) != "") {
              setTimeout(function () {
                redir("", "parent");
              }, 1500);
            } else {
              consultarPropostas();
            }

            preLoadingClose();
            return;
          },
          "json"
        );
      }
    },
  });
}

function confirmarExclusaoCarteiraContratoSerie(
  linhaID,
  contratoID,
  serieID,
  strMensagem
) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem + "<br>Confirma a exclusão do item selecionado?",
    type: BootstrapDialog.TYPE_PRIMARY,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-primary",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddCarteirasContratosSerieExcluir").val()),
          {
            CTO_ID: $.trim(contratoID),
            CTS_ID: $.trim(serieID),
          },
          function (data) {
            //alert(data); return;
            $(".modal").modal("hide");
            preLoadingClose();

            if (data.sucesso == "true") {
              $("#" + linhaID).hide();
              $("#" + linhaID).html("");
              $.notify(data.mensagem, "success");
            } else {
              $.notify(data.mensagem, "error");
            }
          },
          "json"
        );
      }
    },
  });
}

function confirmarExclusaoCarteiraContratoApropriacao(
  linhaID,
  contratoID,
  apropriacaoID,
  strMensagem
) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem + "<br>Confirma a exclusão do item selecionado?",
    type: BootstrapDialog.TYPE_PRIMARY,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-primary",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddCarteirasContratosApropriacaoExcluir").val()),
          {
            CTO_ID: $.trim(contratoID),
            CTA_ID: $.trim(apropriacaoID),
          },
          function (data) {
            //alert(data); return;
            $(".modal").modal("hide");
            preLoadingClose();

            if (data.sucesso == "true") {
              $("#" + linhaID).hide();
              $("#" + linhaID).html("");
              $(".calcularValorParcelaProposta").trigger("blur");

              $.notify(data.mensagem, "success");
            } else {
              $.notify(data.mensagem, "error");
            }
          },
          "json"
        );
      }
    },
  });
}

function consultarCarteiraContratosParcelas() {
  $(".btn-formulario").prop("disbled", true);
  $("#tab_parcelas").html(strCarregando);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#carteiras_contratos_parcelas").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      CTO_ID: $.trim($("#CTO_ID").val()),
    },
  })
    .success(function (data) {
      $(".btn-formulario").prop("disbled", false);
      preLoadingClose();

      if (data.error) {
        $("#tab_parcelas").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#tab_parcelas").html(data.strHtml);
      setInitFunctions();
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disbled", false);
      $("#tab_parcelas").html("");
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function confirmarCapturarTerreno(url) {
  if ($.trim($("#CAX_Gestor_ID").val()) == "") {
    $.notify("Gestor precisa ser informado.", "warn");
    return;
  } else {
    var strHtml =
      "<br>Observações: <textarea class='form-control' rows='3' name='OBS_Captura' id='OBS_Captura' placeholder='Informe aqui o motivo da captura (opcional)'></textarea>";

    BootstrapDialog.confirm({
      title: strInformacao,
      message: "Confirma a captura do terreno ?" + strHtml,
      type: BootstrapDialog.TYPE_PRIMARY,
      closable: true,
      draggable: true,
      btnCancelLabel: strLabelNao,
      btnOKLabel: strLabelSim,
      btnOKClass: "btn-primary",
      callback: function (result) {
        if (result) {
          preLoadingOpen();

          $.post(
            url,
            {
              CAX_ID: $.trim($("#CAX_Gestor_ID").val()),
              OBS_Captura: $.trim($("#OBS_Captura").val()),
            },
            function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                $.notify(data.mensagem, "success");

                setTimeout(function () {
                  redir(data.redir, "parent");
                }, 1500);
              } else {
                $.notify(data.mensagem, "error");
              }

              $(".modal").modal("hide");
              preLoadingClose();
              return;
            },
            "json"
          );
        }
      },
    });
  }
}

function confirmarRecusarTerreno(url) {
  if ($.trim($("#CAX_Gestor_ID").val()) == "") {
    $.notify("Gestor precisa ser informado.", "warn");
    return;
  } else {
    var strHtml =
      "<br>Observações: <textarea class='form-control' rows='3' name='OBS_Captura' id='OBS_Captura' placeholder='Informe aqui o motivo da captura (opcional)'></textarea>";

    BootstrapDialog.confirm({
      title: strInformacao,
      message: "Confirma recusar a captura do terreno ?" + strHtml,
      type: BootstrapDialog.TYPE_DANGER,
      closable: true,
      draggable: true,
      btnCancelLabel: strLabelNao,
      btnOKLabel: strLabelSim,
      btnOKClass: "btn-danger",
      callback: function (result) {
        if (result) {
          preLoadingOpen();

          $.post(
            url,
            {
              CAX_ID: $.trim($("#CAX_Gestor_ID").val()),
              OBS_Captura: $.trim($("#OBS_Captura").val()),
            },
            function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                $.notify(data.mensagem, "success");

                setTimeout(function () {
                  redir(data.redir, "parent");
                }, 1000);
              } else {
                $.notify(data.mensagem, "error");
              }

              $(".modal").modal("hide");
              preLoadingClose();
              return;
            },
            "json"
          );
        }
      },
    });
  }
}

function consultarCarteiraContratosLog() {
  preLoadingOpen();
  $("#tab_logs").html(strCarregando);

  $.post(
    $.trim($("#hddCarteirasContratosLogs").val()),
    {
      CTO_ID: $.trim($("#CTO_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#tab_logs").html(data.strHtml);
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
    },
    "json"
  );
}

function consultarContratosCarteirasParcelasBaixas() {
  var strLabel = consultarPadraoInicial();
  var arrContratos = new Array();

  $("select[name='CTO_ID[]'] option:selected").each(function () {
    arrContratos.push($(this).val());
  });

  $.post(
    $.trim($("#hddCarteirasContratosParcelasBaixaConsultar").val()),
    {
      CTO_ID: arrContratos,
      CPB_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      CPB_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
      CPB_DataBaixaInicial: $.trim($("#txtDataBaixaInicial").val()),
      CPB_DataBaixaFinal: $.trim($("#txtDataBaixaFinal").val()),
      FlagCobrancaFacil: $.trim($("#FlagCobrancaFacil").val())
    },
    function (data) {
      if (data.sucesso == "true") {
        consultarPadraoSucesso(strLabel);
        consultarPadraoSucessoPaginacao(data, true);
      } else {
        consultarPadraoExcessao();
        consultarPadraoFalha(strLabel);
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function consultarCarteiraContratosParcelasPorContrato() {
  $(".btn-formulario").prop("disabled", true);
  $("#consultar-parcelas").html(strCarregando);
  preLoadingOpen();

  if ($.trim($("#CTO_ID").val()) != "") {
    var strRenegociacao = $("#hddRenegociacao").val();
    var strCalculoAntecipacao = $("#hddCalculoAntecipacao").val();

    $.ajax({
      url: $.trim($("#carteiras_contratos_parcelas").val()),
      dataType: "json",
      cache: false,
      data: {
        CTO_ID: $.trim($("#CTO_ID").val()),
        CTP_Situacao: $.trim($("#hddBaixado").val()),
        CTO_Renegociacao: strRenegociacao,
        CTO_CalculoAntecipacao: strCalculoAntecipacao,
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();

        if (data.error) {
          $("#consultar-parcelas").html("");
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $("#consultar-parcelas").html(data.strHtml);

        if (data.antecipacao == true) {
          $('input[name="items[]"]').prop("disabled", true);
        }

        setInitFunctions();
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#consultar-parcelas").html("");

        dialogAlert(strAtencao, data.responseText, 6);
      });
  } else {
    preLoadingClose();
    $("#consultar-parcelas").html("");
    $.notify("Contrato precisa ser informado.", "error");
  }
}

function exibirFormularioCarteiraContratosParcelasBaixas(parcelaID) {
  //preLoadingOpen();

  $.post(
    $.trim($("#hddCarteirasContratosParcelasConsultar").val()),
    {
      CTO_ID: $.trim($("#CTO_ID").val()),
    },
    function (data) {
      // alert(data); return;
      if (data.sucesso == "true") {
        BootstrapDialog.confirm({
          title: strInformacao,
          message: data.strHtml,
          type: BootstrapDialog.TYPE_PRIMARY,
          closable: true,
          draggable: true,
          btnCancelLabel: strLabelNao,
          btnOKLabel: strLabelSim,
          btnOKClass: "btn-primary",
          callback: function (result) {
            if (result) {
              preLoadingOpen();

              $.post(
                url,
                {
                  CAX_ID: $.trim($("#CAX_Gestor_ID").val()),
                },
                function (data) {
                  //alert(data); return;
                  $(".modal").modal("hide");
                  preLoadingClose();
                  if (data.sucesso == "true") {
                    redir(data.redir, "parent");
                    //$.notify(data.mensagem, "success");
                  } else {
                    $.notify(data.mensagem, "error");
                  }
                },
                "json"
              );
            }
          },
        });
      } else {
        $("#consultar-parcelas").html("");
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
    },
    "json"
  );
}

function aprovarCarteiraContrato(contratoID, strMensagem) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_SUCCESS,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-success",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddCarteirasContratosAprovar").val()),
          {
            CTO_ID: contratoID,
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              $.notify(data.mensagem, "success");

              if ($.trim($("#CTO_ID").val()) != "") {
                setTimeout(function () {
                  redir("", "parent");
                }, 1500);
              } else {
                consultarContratosCarteiras();
              }
            } else {
              $.notify(data.mensagem, "error");
            }
            preLoadingClose();
            return;
          },
          "json"
        );
      }
    },
  });
}

function reprovarCarteiraContrato(contratoID, strMensagem) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_DANGER,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-danger",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddCarteirasContratosReprovar").val()),
          {
            CTO_ID: contratoID,
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              $.notify(data.mensagem, "success");
            } else {
              $.notify(data.mensagem, "error");
            }

            if ($.trim($("#CTO_ID").val()) != "") {
              setTimeout(function () {
                redir("", "parent");
              }, 1500);
            } else {
              consultarContratosCarteiras();
            }
            preLoadingClose();
            return;
          },
          "json"
        );
      }
    },
  });
}

function confirmarCarteiraParcelaBaixaPagamento(parcelaID, strTitulo) {
  $.ajax({
    url: $.trim($("#hddCarteirasContratosConfirmarBaixa").val()),
    dataType: "json",
    cache: false,
    data: {
      CTO_ID: $.trim($("#CTO_ID").val()),
      CTP_ID: $.trim(parcelaID),
    },
    type: "POST",
  })
    .success(function (data) {
      $("button").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      BootstrapDialog.show({
        title: strTitulo,
        message: data.strHtml,
        size: BootstrapDialog.SIZE_WIDE,
        buttons: [
          {
            label: "Não",
            id: "btn-confirmar-nao",
            action: function (dialogItself) {
              dialogItself.close();
            },
          },
          {
            label: "Sim",
            cssClass: "btn-primary",
            id: "btn-confirmar-sim",
            data: {
              js: "btn-confirm",
              "user-id": "3",
            },
            action: function () {
              if ($.trim($("#CON_ID").val()) == "") {
                $.notify("Conta bancária ser informada.", "warn");
                return;
              } else if ($.trim($("#CPB_DataBaixa").val()) == "") {
                $.notify("Data da baixa precisa ser informada.", "warn");
                return;
              } else {
                $("#btn-confirmar-nao, #btn-confirmar-sim").prop("disabled", true);
                var strLabel = $("#btn-confirmar-sim").html();
                $("#btn-confirmar-sim").html(strCarregando);

                var arrDados = new FormData();
                arrDados.append("CTO_ID", $.trim($("#CTO_ID").val()));
                arrDados.append("CON_ID", $.trim($("#CON_ID").val()));
                arrDados.append("CPB_DataBaixa", $.trim($("#CPB_DataBaixa").val()));
                arrDados.append("CTP_ID", $.trim(parcelaID));
                arrDados.append("CPB_ValorRecebido", $.trim($("#CPB_ValorRecebido").val()));
                arrDados.append("CPB_ValorAcrescimo", $.trim($("#CPB_ValorAcrescimo").val()));
                arrDados.append("CPB_ValorJuros", $.trim($("#CPB_ValorJuros").val()));
                arrDados.append("CPB_ValorMulta", $.trim($("#CPB_ValorMulta").val()));
                arrDados.append("CPB_ValorDesconto", $.trim($("#CPB_ValorDesconto").val()));
                arrDados.append("CPB_ValorTotal", $.trim($("#CPB_ValorTotal").val()));
                arrDados.append("CPB_Observacoes", $.trim($("#CPB_Observacoes").val()));
                //Anexos (opcional)
                $("input[type=text][name='SGP_Descricao[]']").each(function () {
                  arrDados.append("SGP_Descricao[]", $.trim($(this).val()));
                });

                $("input[type=file][name='SGP_Arquivos[]']").each(function () {
                  if ($.trim($(this).prop("files")[0]) != "") {
                    arrDados.append("SGP_Arquivos[]", $(this).prop("files")[0]);
                  }
                });

                $.ajax({
                  url: $.trim($("#hddCarteirasContratosBaixarParcela").val()),
                  dataType: "json",
                  cache: false,
                  contentType: false,
                  processData: false,
                  data: arrDados,
                  type: "POST",
                }).success(function (data) {
                    $("#btn-confirmar-nao, #btn-confirmar-sim").prop("disabled", false);
                    $("#btn-confirmar-sim").html(strLabel);

                    if (data.error) {
                      dialogAlert(strAtencao, data.error.msg, 6);
                      return;
                    }

                    $(".modal").modal("hide");
                    $.notify(data.mensagem, "success");

                    setTimeout(function () {
                      redir("");
                    }, 1500);
                  }).fail(function (data) {
                    $("#btn-confirmar-nao, #btn-confirmar-sim").prop("disabled", false);
                    $("#btn-confirmar-sim").html(strLabel);

                    dialogAlert(strAtencao, data.responseText, 6);
                  });
              }
            },
          },
        ],
      });

      setTimeout(function () {
        $('#CON_ID').chosen();
        setInitFunctions();
      }, 2000);
    })
    .fail(function (data) {
      $("button").prop("disabled", false);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function confirmarCancelarCarteiraParcelaBaixaPagamento(
  contratoID,
  parcelaID,
  strMensagem,
  acao,
  baixaId = null
) {
  var strHtml =
    "<form class='form-horizontal' id='frmFormularioDialog' method='post' onSubmit='return false;' enctype='multipart/form-data'>";
  strHtml += strMensagem + "<br>";
  strHtml +=
    "Observações: <textarea class='form-control' rows='3' name='strObservacoes' id='strObservacoes' placeholder='Informe aqui a observação'></textarea>";
  strHtml += "</form>";

  BootstrapDialog.show({
    title: strInformacao,
    message: strHtml,
    size: BootstrapDialog.SIZE_WIDE,
    type: BootstrapDialog.TYPE_DANGER,
    buttons: [
      {
        label: "Não",
        id: "btn-confirmar-nao",
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: "Sim",
        id: "btn-confirmar-sim",
        cssClass: "btn-danger",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          $("#btn-confirmar-sim, #btn-confirmar-nao").prop("disabled", true);
          var strLabel = $("#btn-confirmar-sim").html();
          $("#btn-confirmar-sim").html(strCarregando);

          $.ajax({
            url: $.trim($("#hddCarteiraParcelaBaixaCancelar").val()),
            dataType: "json",
            cache: false,
            data: {
              CTO_ID: contratoID,
              CTP_ID: parcelaID,
              CPB_Observacoes: $.trim($("#strObservacoes").val()),
              CPB_ID: baixaId,
            },
            type: "POST",
          })
            .success(function (data) {
              $("#btn-confirmar-sim, #btn-confirmar-nao").prop(
                "disabled",
                false
              );
              $("#btn-confirmar-sim").html(strLabel);

              if (data.error) {
                dialogAlert(strInformacao, data.error.msg, 6);
                return;
              }

              $(".modal").modal("hide");
              $.notify(data.mensagem, "success");

              if (acao == 1) {
                consultarContratosCarteirasParcelasBaixas();
              } else {
                consultarCarteiraContratosParcelas();
              }
            })
            .fail(function (data) {
              $("#btn-confirmar-sim, #btn-confirmar-nao").prop(
                "disabled",
                false
              );
              $("#btn-confirmar-sim").html(strLabel);

              dialogAlert(strAtencao, data.responseText, 6);
            });
        },
      },
    ],
  });
}

function consultarContratosCarteirasInadimplencias() {
  var strLabel = consultarPadraoInicial();
  var arrEmpresas = new Array();
  var arrClientes = new Array();
  var arrContratos = new Array();
  var arrFormas = new Array();
  var arrPeriodicidades = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='ENT_ID[]'] option:selected").each(function () {
    arrClientes.push($(this).val());
  });

  $("select[name='CTO_ID[]'] option:selected").each(function () {
    arrContratos.push($(this).val());
  });

  $("select[name='SGP_FormaPagto[]'] option:selected").each(function () {
    arrFormas.push($(this).val());
  });

  $("select[name='SGP_Periodicidade[]'] option:selected").each(function () {
    arrPeriodicidades.push($(this).val());
  });

  $.ajax({
    url: $.trim(
      $("#carteiras_contratos_parcelas_inadimplencias_consultar").val()
    ),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: arrEmpresas,
      ENT_ID: arrClientes,
      CTO_ID: arrContratos,
      SGP_DataVencimentoInicial: $.trim($("#txtDataInicial").val()),
      SGP_DataVencimentoFinal: $.trim($("#txtDataFinal").val()),
      SGP_FormaPagamento: arrFormas,
      SGP_Periodicidade: arrPeriodicidades,
    },
    type: "POST",
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);

      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data, true);
    })
    .fail(function (data) {
      consultarPadraoExcessao();
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarParcelasContasPagar(CPG_Baixar = null) {
  $(".btn-formulario").prop("disabled", true);
  $("#tab_parcelas").html(strCarregando);
  preLoadingOpen();
  
  if(CPG_Baixar != null){
    CPG_Baixar = true;
  }

  $.ajax({
    url: $.trim($("#contas_pagar_parcelas").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_ID: $.trim($("#CPG_ID").val()),
      CPG_Baixar: CPG_Baixar
      //#CPG_Numero: $.trim($('#CPG_Numero').val()),
      //CPG_Valor: $.trim($('#CPG_Valor').val())
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        $("#tab_parcelas").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#tab_parcelas").html(data.strHtml);

      if (data.aprovado == true) {
        $("input, select, textarea").prop("disabled", true);
        $("button checkbox").hide();
        $("input[name='CPG_DataEmissao[]']").prop("disabled", false);
        $("input[name='CPP_Valor[]']").prop("disabled", false);
        $("input[name='CPP_DataVencimento[]']").prop("disabled", false);
      }

      setInitFunctions();
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#tab_parcelas").html("");
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function confirmarExcluirParcelasContasPagar(strMensagem) {
  var arrSelecionados = new Array();

  $("input[type=checkbox][name='items[]']:checked").each(function () {
    arrSelecionados.push($(this).val());
  });

  if (arrSelecionados.length > 0) {
    BootstrapDialog.confirm({
      title: strInformacao,
      message: strMensagem,
      type: BootstrapDialog.TYPE_PRIMARY,
      closable: true,
      draggable: true,
      btnCancelLabel: strLabelNao,
      btnOKLabel: strLabelSim,
      btnOKClass: "btn-primary",
      callback: function (result) {
        if (result) {
          preLoadingOpen();

          $.ajax({
            url: $.trim($("#hddFinanceiroParcelasContasPagarExcluir").val()),
            dataType: "json",
            cache: false,
            data: {
              CPG_ID: $.trim($("#CPG_ID").val()),
              CPP_ID: arrSelecionados,
            },
            type: "POST",
          })
            .success(function (data) {
              preLoadingClose();

              if (data.error) {
                dialogAlert(strAtencao, data.error.msg, 6);
                return;
              }

              consultarParcelasContasPagar();
              $.notify(data.mensagem, "success");

            }).fail(function (data) {
              preLoadingClose();
              dialogAlert(strAtencao, data.responseText, 6);
            });
        }
      },
    });
  } else {
    $.notify("Selecione no minímo 1 (UMA) opção para excluir.", "warn");
    return;
  }
}

function adicionarParcelaContasPagarFinanceiro() {
  $(".btn-formulario").prop("disabled", true);
  var strLabel = $("#btnAdicionarParcela").html();
  $("#btnAdicionarParcela").html(strCarregando);
  //preLoadingOpen();

  $.ajax({
    url: $.trim($("#contas_pagar_parcelas_adicionar").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_ID: $.trim($("#CPG_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $("#btnAdicionarParcela").html(strLabel);
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3);

      setTimeout(function () {
        $("#SGP_Periodicidade").chosen();
        setInitFunctions();
      }, 500);
    })
    .fail(function (data) {
      $("#btnAdicionarParcela").html(strLabel);
      $(".btn-formulario").prop("disabled", false);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarParcelaContasPagarFinanceiro() {
  if ($.trim($("#SGP_Periodicidade").val()) == "") {
    $.notify("Periodicidade precisa ser informada.", "warn");
    return;
  } else if (
    $.trim($("#CPP_QuantidadeParcelas").val()) == "" ||
    $.trim($("#CPP_QuantidadeParcelas").val()) == "0"
  ) {
    $.notify("Quantidade da parcela precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CPP_DataVencimento").val()) == "") {
    $.notify("Data do primeiro vencimento precisa ser informado.", "warn");
    return;
  } else {
    $("#btnSalvarParcelas").prop("disabled", true);
    var strLabel = $("#btnSalvarParcelas").html();
    $("#btnSalvarParcelas").html(strCarregandoIcone);

    $.ajax({
      url: $.trim($("#contas_pagar_parcelas_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        CPG_ID: $.trim($("#CPG_ID").val()),
        SGP_Periodicidade: $.trim($("#SGP_Periodicidade").val()),
        CPP_QuantidadeParcelas: $.trim($("#CPP_QuantidadeParcelas").val()),
        CPP_DataVencimento: $.trim($("#CPP_DataVencimento").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarParcelas").prop("disabled", false);
        $("#btnSalvarParcelas").html(strLabel);
        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $(".modal").modal("hide");
        consultarParcelasContasPagar();
      })
      .fail(function (data) {
        $("#btnSalvarParcelas").prop("disabled", false);
        $("#btnSalvarParcelas").html(strLabel);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarApropriacoesContasPagar() {
  $(".btn-formulario").prop("disabled", true);
  $("#cntConsultaApropriacoesContasPagar").html(strCarregando);

  $.ajax({
    url: $.trim($("#contas_pagar_apropriacoes").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_ID: $.trim($("#CPG_ID").val()),
      CPG_Valor: $.trim($("#CPG_Valor").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        $("#cntConsultaApropriacoesContasPagar").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#cntConsultaApropriacoesContasPagar").html(data.strHtml);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#cntConsultaApropriacoesContasPagar").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function adicionarApropriacoesContasPagar() {
  $(".btn-formulario").prop("disabled", true);
  $("#tab_apropriacoes").html(strCarregando);
  $("#CPA_ID").val("");
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#hddFinanceiroApropriacoesContasPagarAdicionar").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_ID: $.trim($("#CPG_ID").val()),
      EMP_ID: $.trim($("#EMP_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#tab_apropriacoes").html(data.strHtml);
      consultarApropriacoesContasPagar();

      setTimeout(function () {
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").chosen("destroy");
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").prop("selectedindex", -1);
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").chosen();

        $("#SGP_Percentual").blur(function () {
          $(".btn-formulario, #btnSalvarContasPagarApropriacao").prop(
            "disabled",
            true
          );

          $.ajax({
            url: $.trim(
              $.trim($("#hddFinanceiroApropriacoesContasPagarCalcular").val())
            ),
            dataType: "json",
            cache: false,
            data: {
              CPG_ID: $.trim($("#CPG_ID").val()),
              CPA_ID: $.trim($("#CPA_ID").val()),
              SGP_Percentual: $.trim($(this).val()),
            },
            type: "POST",
          })
            .success(function (data) {
              $(".btn-formulario, #btnSalvarContasPagarApropriacao").prop(
                "disabled",
                false
              );

              if (data.error) {
                dialogAlert(strInformacao, data.error.msg, 6);
                return;
              }

              $("#SGP_PercentualTotal").val(data.douValorTotal);
              $(".btn-formulario, #btnSalvarContasPagarApropriacao").prop(
                "disabled",
                false
              );
            })
            .fail(function (data) {
              $(".btn-formulario, #btnSalvarContasPagarApropriacao").prop(
                "disabled",
                false
              );

              dialogAlert(strAtencao, data.responseText, 6);
            });
        });

        $("#ORC_ID2").change(function () {
          $("#OCI_ID2").html("");
          $("#OCI_ID2").append(
            "<option value=''>" + strSelecione + "</option>"
          );

          var valor = $.trim(this.value);

          if (valor != "") {
            $.ajax({
              url: $.trim($.trim($("#hddApropriacoesDados").val())),
              dataType: "json",
              cache: false,
              data: {
                ORC_ID: valor,
              },
              type: "POST",
            })
              .success(function (data) {
                if (data.error) {
                  dialogAlert(strInformacao, data.error.msg, 6);
                  return;
                }

                if (data.arrDados.length != undefined) {
                  var strHtml = "";
                  for (var i = 0; i < data.arrDados.length; i++) {
                    strHtml += "<option ";

                    if (data.arrDados.length == 1) {
                      strHtml += " selected ";
                    }

                    strHtml +=
                      " value='" +
                      data.arrDados[i].OCI_ID +
                      "'>" +
                      data.arrDados[i].OCI_Codigo +
                      " - " +
                      data.arrDados[i].OCI_Descricao +
                      "</option>";
                  }

                  $("#OCI_ID2").append(strHtml);
                }

                $("#OCI_ID2").trigger("chosen:updated");
              })
              .fail(function (data) {
                $("#OCI_ID2").trigger("chosen:updated");
                dialogAlert(strAtencao, data.responseText, 6);
              });
          }
        });

        $("#OCI_ID2").change(function () {
          if ($.trim(this.value) != "") {
            $.post(
              $.trim(
                $("#orcamentos_plano_financeiro_por_item_orcamento").val()
              ),
              {
                ORC_ID: $("#ORC_ID2").val(),
                OCI_ID: $.trim(this.value),
              },
              function (data) {
                //alert(data); return;
                if (data.sucesso == "true") {
                  $("#PLF_Conta2").val(data.PLF_Conta);
                }

                $("#PLF_Conta2").trigger("chosen:updated");
              },
              "json"
            );
          }
        });

        setInitFunctions();
      }, 500);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarApropriacaoContasPagar() {
  if ($.trim($("#CEN_ID").val()) == "") {
    $.notify("Centro de custo precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#PLF_Conta2").val()) == "") {
    $.notify("Plano Financeiro precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SGP_Percentual").val()) == "") {
    $.notify("Percentual precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#checkOrcamentoObrigatorio").val()) == "S" &&
    $.trim($("#ORC_ID2").val()) == ""
  ) {
    $.notify("Orçamento precisa ser informado.", "warn");
  } else if (
    $.trim($("#checkOrcamentoObrigatorio").val()) == "S" &&
    $.trim($("#OCI_ID2").val()) == ""
  ) {
    $.notify("Item do Orçamento precisa ser informado.", "warn");
  } else {
    $("#btnSalvarContasPagarApropriacao").prop("disabled", true);
    var strLabel = $("#btnSalvarContasPagarApropriacao").html();
    $("#btnSalvarContasPagarApropriacao").html(strCarregando);
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#contas_pagar_apropriacoes_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        CPA_ID: $.trim($("#CPA_ID").val()),
        CPG_ID: $.trim($("#CPG_ID").val()),
        CEN_ID: $.trim($("#CEN_ID").val()),
        ORC_ID: $.trim($("#ORC_ID2").val()),
        OCI_ID: $.trim($("#OCI_ID2").val()),
        PLF_Conta: $.trim($("#PLF_Conta2").val()),
        CPA_Percentual: $.trim($("#SGP_Percentual").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarContasPagarApropriacao").prop("disabled", false);
        $("#btnSalvarContasPagarApropriacao").html(strLabel);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");
        $(
          "#CPA_ID, #CEN_ID, #ORC_ID2, #PLF_Conta2, #SGP_Percentual, #OCI_ID2"
        ).val("");
        $("#OCI_ID2").html("<option value=''>" + strSelecione + "</option>");
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").trigger("chosen:updated");

        consultarApropriacoesContasPagar();
      })
      .fail(function (data) {
        $("#btnSalvarContasPagarApropriacao").prop("disabled", false);
        $("#btnSalvarContasPagarApropriacao").html(strLabel);
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function editarApropriacaoContasPagar(intCodigo) {
  $(".btn-formulario, #btnSalvarContasPagarApropriacao").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#hddFinanceiroApropriacoesContasPagarEditar").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_ID: $.trim($("#CPG_ID").val()),
      CPA_ID: $.trim(intCodigo),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario, #btnSalvarContasPagarApropriacao").prop(
        "disabled",
        false
      );
      preLoadingClose();

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#CPA_ID").val(intCodigo);
      $("#CPG_ID").val($.trim($("#CPG_ID").val()));
      $("#CEN_ID").val(data.arrDados[0].CEN_ID);
      $("#ORC_ID2").val(data.arrDados[0].ORC_ID);
      $("#ORC_ID2").trigger("change");
      $("#PLF_Conta2").val(data.arrDados[0].PLF_Conta);
      $("#SGP_Percentual").val(data.arrDados[0].CPA_Percentual);

      setTimeout(function () {
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").chosen("destroy");
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").prop("selectedindex", -1);
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").chosen();
        $("#OCI_ID2").val(data.arrDados[0].OCI_ID);
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").trigger("chosen:updated");
      }, 500);
    })
    .fail(function (data) {
      $(".btn-formulario, #btnSalvarContasPagarApropriacao").prop(
        "disabled",
        false
      );
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function confirmarAprovacaoContasPagar(
  pagarID,
  strMensagem,
  tipo,
  orcamentoVinculado,
  adiantamento
) {
  var strTitulo = "";

  if (tipo == 1 || orcamentoVinculado == 1) {
    strTitulo += '<div class="alert alert-warning alert-dismissible">';
    strTitulo +=
      '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>';
    strTitulo +=
      '<h4><i class="icon fa fa-warning"></i> ' + strInformacao + "</h4>";
  }

  if (tipo == 1) {
    strTitulo += "Este título não tem nenhum anexo vinculado.<br>";
  }

  if (orcamentoVinculado == 1) {
    strTitulo += "Não existe nenhum orçamento vinculado as apropriações.<br>";
  }

  if (tipo == 1 || orcamentoVinculado == 1) {
    strTitulo += "</div><br>";
  }

  if (adiantamento == 1) {
    strTitulo += '<div class="alert alert-danger alert-dismissible">';
    strTitulo +=
      '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>';
    strTitulo +=
      '<h4><i class="icon fa fa-danger"></i> ' + strInformacao + "</h4>";
    strTitulo += "Existe Adiantamento para esse fornecedor.<br>";
    strTitulo += "</div><br>";
  }

  strMensagem = strTitulo + strMensagem;

  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_PRIMARY,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-primary",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.ajax({
          url: $.trim($("#hddFinanceiroContasAprovar").val()),
          dataType: "json",
          cache: false,
          data: {
            CPG_ID: $.trim(pagarID),
          },
          type: "POST",
        })
          .success(function (data) {
            if (data.error) {
              preLoadingClose();
              dialogAlert(strAtencao, data.error.msg, 6);
              return;
            }

            if ($.trim($("#CPG_ID").val()) != "") {
              setTimeout(function () {
                preLoadingClose();
                redir("", "parent");
              }, 2000);
            } else {
              consultarContasPagar();
            }

            $.notify(data.mensagem, "success");
          })
          .fail(function (data) {
            preLoadingClose();
            dialogAlert(strAtencao, data.responseText, 6);
          });
      }
    },
  });
}

function confirmarReprovacaoContasPagar(pagarID, strMensagem) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_DANGER,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-danger",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddFinanceiroContasReprovar").val()),
          {
            CPG_ID: $.trim(pagarID),
          },
          function (data) {
            //alert(data); return;
            preLoadingClose();
            if (data.sucesso == "true") {
              if ($.trim($("#CPG_ID").val()) != "") {
                setTimeout(function () {
                  preLoadingClose();
                  redir("", "parent");
                }, 500);
              } else {
                consultarContasPagar();
              }

              $.notify(data.mensagem, "success");
            } else {
              dialogAlert(strAtencao, data.mensagem, 6);
            }
          },
          "json"
        );
      }
    },
  });
}

function consultarContasPagarBaixa() {
  var strLabel = consultarPadraoInicial();
  var arrEmpresas = new Array();
  var arrContasBancarias = new Array();
  var arrEntidades = new Array();
  var arrCentroCustos = new Array();
  var arrPlanosFinanceiros = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='ENT_ID[]'] option:selected").each(function () {
    arrEntidades.push($(this).val());
  });

  $("select[name='PLF_Conta[]'] option:selected").each(function () {
    arrPlanosFinanceiros.push($(this).val());
  });

  $("select[name='CEN_ID[]'] option:selected").each(function () {
    arrCentroCustos.push($(this).val());
  });

  $("select[name='CON_ID[]'] option:selected").each(function () {
    arrContasBancarias.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#contas_pagar_baixa_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: arrEmpresas,
      ENT_ID: arrEntidades,
      CEN_ID: arrCentroCustos,
      CON_ID: arrContasBancarias,
      PLF_Conta: arrPlanosFinanceiros,
      SGP_Numero: $.trim($("#CPG_Numero2").val()),
      SGP_DataBaixaInicial: $.trim($("#txtDataInicial").val()),
      SGP_DataBaixaFinal: $.trim($("#txtDataFinal").val()),
    },
    type: "POST",
  }).success(function (data) {
    consultarPadraoSucesso(strLabel);

    if (data.error) {
      consultarPadraoExcessao();
      dialogAlert(strInformacao, data.error.msg, 6);
      return;
    }

    consultarPadraoSucessoPaginacao(data);
  }).fail(function (data) {
    consultarPadraoFalha(strLabel);
    dialogAlert(strAtencao, data.responseText, 6);
  });
}

function consultarFinanceiroParcelasPorContasPagar() {
  $(".btn-filtro").prop("disabled", true);
  preLoadingOpen();

  var strLabel = $("#btnSalvar").html();
  $("#btnSalvar, #consultar-parcelas").html(strCarregando);

  var arrParcelas = new Array();
  var arrCentroCustos = new Array();
  var arrPlanosFinanceiros = new Array();
  var arrCadastros = new Array();

  $("select[name='CPP_NumeroParcela[]'] option:selected").each(function () {
    arrParcelas.push($(this).val());
  });

  $("select[name='PLF_Conta[]'] option:selected").each(function () {
    arrPlanosFinanceiros.push($(this).val());
  });

  $("select[name='CEN_ID[]'] option:selected").each(function () {
    arrCentroCustos.push($(this).val());
  });

  $("select[name='CAX_ID[]'] option:selected").each(function () {
    arrCadastros.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#contas_pagar_parcelas").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_ID: $.trim($("#CPG_ID").val()),
      EMP_ID: $.trim($("#EMP_ID").val()),
      ENT_ID: $.trim($("#ENT_ID").val()),
      CAX_ID: arrCadastros,
      PLF_Conta: arrPlanosFinanceiros,
      CEN_ID: arrCentroCustos,
      CPP_NumeroParcela: arrParcelas,
      CPP_DataVencimentoInicial: $.trim($("#txtDataInicial").val()),
      CPP_DataVencimentoFinal: $.trim($("#txtDataFinal").val()),
      CPG_Baixar: strSim,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#btnSalvar").html(strLabel);
      preLoadingClose();

      if (data.error) {
        $("#consultar-parcelas").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#consultar-parcelas").html(data.strHtml);
    })
    .fail(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#btnSalvar").html(strLabel);
      $("#consultar-parcelas").html("");
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarMovimentacoes() {
  var strLabel     = consultarPadraoInicial();
  var arrEmpresas  = new Array();
  var arrContas    = new Array();
  var arrOperacoes = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='CON_ID[]'] option:selected").each(function () {
    arrContas.push($(this).val());
  });

  $("select[name='MOV_Operacao[]'] option:selected").each(function () {
    arrOperacoes.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#hddMovimentacoesConsultar").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: arrEmpresas,
      CON_ID: arrContas,
      MOV_Numero: $.trim($("#MOV_Numero").val()),
      MOV_Operacoes: arrOperacoes,
      MOV_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      MOV_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
      MOV_DataEmissaoInicial: $.trim($("#txtDataEmissaoInicial").val()),
      MOV_DataEmissaoFinal: $.trim($("#txtDataEmissaoFinal").val()),
      MOV_SemApropriacoes: $.trim($("#MOV_SemApropriacoes").val()),
    },
    type: "POST",
  }).success(function (data) {
      consultarPadraoSucesso(strLabel);
      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
    }).fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarApropriacoesMovimentacoes() {
  $(".btn-formulario").prop("disabled", true);
  $("#cntConsultaApropriacoesMovimentacoes").html(strCarregando);

  $("#MOA_ID, #CEN_ID, #ORC_ID2, #PLF_Conta2, #SGP_Percentual, #OCI_ID2").val(
    ""
  );
  $("#OCI_ID2").html("<option value=''>" + strSelecione + "</option>");
  $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").trigger("chosen:updated");

  $.ajax({
    url: $.trim($("#movimentacoes_apropriacoes").val()),
    dataType: "json",
    cache: false,
    data: {
      MOV_ID: $.trim($("#MOV_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        $("#cntConsultaApropriacoesMovimentacoes").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#cntConsultaApropriacoesMovimentacoes").html(data.strHtml);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#cntConsultaApropriacoesMovimentacoes").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function adicionarApropriacoesMovimentacoes() {
  $(".btn-formulario").prop("disabled", true);
  $("#tab_apropriacoes").html(strCarregando);
  $("#MOA_ID").val("");
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#movimentacoes_apropriacoes_adicionar").val()),
    dataType: "json",
    cache: false,
    data: {
      MOV_ID: $.trim($("#MOV_ID").val()),
      EMP_ID: $.trim($("#EMP_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#tab_apropriacoes").html(data.strHtml);
      consultarApropriacoesMovimentacoes();

      setTimeout(function () {
        setInitFunctions();
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").chosen("destroy");
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").prop("selectedindex", -1);
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").chosen();

        $("#SGP_Percentual").blur(function () {
          $.ajax({
            url: $.trim($("#movimentacoes_apropriacoes_calcular").val()),
            dataType: "json",
            cache: false,
            data: {
              MOV_ID: $.trim($("#MOV_ID").val()),
              MOA_ID: $.trim($("#MOA_ID").val()),
              MOA_Percentual: $.trim($(this).val()),
            },
            type: "POST",
          })
            .success(function (data) {
              if (data.error) {
                dialogAlert(strAtencao, data.error.msg, 6);
                return;
              }

              $("#SGP_PercentualTotal").val(data.douTotal);
            })
            .fail(function (data) {
              dialogAlert(strAtencao, data.responseText, 6);
            });
        });

        $("#ORC_ID2").change(function () {
          $("#OCI_ID2").html("");
          $("#OCI_ID2").append(
            "<option value=''>" + strSelecione + "</option>"
          );

          var valor = $.trim(this.value);

          if (valor != "") {
            $.ajax({
              url: $.trim($("#hddApropriacoesDados").val()),
              dataType: "json",
              cache: false,
              data: {
                ORC_ID: valor,
              },
              type: "POST",
            })
              .success(function (data) {
                if (data.error) {
                  dialogAlert(strAtencao, data.error.msg, 6);
                  return;
                }

                if (data.arrDados != undefined) {
                  var strHtml = "";
                  for (var i = 0; i < data.arrDados.length; i++) {
                    strHtml += "<option ";

                    if (data.arrDados.length == 1) {
                      strHtml += " selected ";
                    }

                    strHtml +=
                      " value='" +
                      data.arrDados[i].OCI_ID +
                      "'>" +
                      data.arrDados[i].OCI_Codigo +
                      " - " +
                      data.arrDados[i].OCI_Descricao +
                      "</option>";
                  }

                  $("#OCI_ID2").append(strHtml);
                }

                $("#OCI_ID2").trigger("chosen:updated");
              })
              .fail(function (data) {
                $("#OCI_ID2").trigger("chosen:updated");
                dialogAlert(strAtencao, data.responseText, 6);
              });
          }
        });
      }, 500);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarApropriacaoMovimentacoes() {
  if ($.trim($("#CEN_ID").val()) == "") {
    $.notify("Centro de custo precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#PLF_Conta2").val()) == "") {
    $.notify("Plano Financeiro precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SGP_Percentual").val()) == "") {
    $.notify("Percentual precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#checkOrcamentoObrigatorio").val()) == "S" &&
    $.trim($("#ORC_ID2").val()) == ""
  ) {
    $.notify("Orçamento precisa ser informado.", "warn");
  } else if (
    $.trim($("#checkOrcamentoObrigatorio").val()) == "S" &&
    $.trim($("#OCI_ID2").val()) == ""
  ) {
    $.notify("Item do Orçamento precisa ser informado.", "warn");
  } else {
    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnSalvarMovimentacoesApropriacao").html();
    $("#btnSalvarMovimentacoesApropriacao").html(strCarregando);
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#movimentacoes_apropriacoes_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        MOA_ID: $.trim($("#MOA_ID").val()),
        MOV_ID: $.trim($("#MOV_ID").val()),
        CEN_ID: $.trim($("#CEN_ID").val()),
        ORC_ID: $.trim($("#ORC_ID2").val()),
        OCI_ID: $.trim($("#OCI_ID2").val()),
        PLF_Conta: $.trim($("#PLF_Conta2").val()),
        MOA_Percentual: $.trim($("#SGP_Percentual").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvarMovimentacoesApropriacao").html(strLabel);
        preLoadingClose();

        if (data.error) {
          $.notify(data.error.msg, "error");
          return;
        }

        $("#btnAdicionarCopiar").hide();
        $(
          "#MOA_ID, #CEN_ID, #ORC_ID2, #PLF_Conta2, #SGP_Percentual, #OCI_ID2"
        ).val("");
        $("#OCI_ID2").html("<option value=''>" + strSelecione + "</option>");

        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").trigger("chosen:updated");
        $.notify(data.mensagem, "success");

        consultarApropriacoesMovimentacoes();
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvarMovimentacoesApropriacao").html(strLabel);
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function editarApropriacaoMovimentacoes(intCodigo) {
  $(".btn-formulario").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#movimentacoes_apropriacoes_editar").val()),
    dataType: "json",
    cache: false,
    data: {
      MOV_ID: $.trim($("#MOV_ID").val()),
      MOA_ID: $.trim(intCodigo),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#MOA_ID").val(data.arrDados[0].MOA_ID);
      $("#ORC_ID2").val(data.arrDados[0].ORC_ID);
      $("#ORC_ID2").trigger("change");

      $("#MOA_ID").val(intCodigo);
      $("#CEN_ID").val(data.arrDados[0].CEN_ID);
      $("#PLF_Conta2").val(data.arrDados[0].PLF_Conta);
      $("#SGP_Percentual").val(data.arrDados[0].MOA_Percentual);

      $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").chosen("destroy");
      $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").prop("selectedindex", -1);
      $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").chosen();

      setTimeout(function () {
        $("#OCI_ID2").val(data.arrDados[0].OCI_ID);
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").trigger("chosen:updated");
        $("#SGP_Percentual").trigger("blur");
      }, 500);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function financeiroBaixaCalcular(
  valorAcrescimo,
  valorJuros,
  valorMulta,
  valorDesconto
) {
  $(".btn-filtro").prop("disabled", true);
  $("#CPB_ValorTotal").val("");

  $.ajax({
    url: $.trim($("#hddFinanceiroParcelaBaixaCalcular").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_ID: $.trim($("#hddCodigo").val()),
      CPP_ID: $.trim($("#hddParcela").val()),
      CPB_ValorRecebido: $("#CPB_ValorRecebido").val(),
      CPB_ValorAcrescimo: valorAcrescimo.val(),
      CPB_ValorJuros: valorJuros.val(),
      CPB_ValorMulta: valorMulta.val(),
      CPB_ValorDesconto: valorDesconto.val(),
    },
    type: "POST",
  }).success(function (data) {
      $(".btn-filtro").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#CPB_ValorTotal").val(data.douValorTotal);
    }).fail(function (data) {
      $(".btn-filtro").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarContasPagarBaixa(e) {
  if (e.keyCode == 13){
    $("#txtDataInicial, #txtDataFinal").val("");
    consultarContasPagarBaixa();
  }
}

function enterPesquisarMovimentos(e) {
  if (e.keyCode == 13) {
    $("#txtDataInicial, #txtDataFinal").val("");

    consultarMovimentacoes();
  }
}

function consultarExtratos() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CON_ID").val()) == "") {
    $.notify("Conta bancária precisa ser informada.", "warn");
    return;
  } else {
    $(".btn-filtro").prop("disabled", true);
    var strLabel = $("#btnFiltrar").html();
    $("#btnFiltrar, #consultar-dados").html(strCarregando);
    preLoadingOpen();

    var strExibirObservacoes = strNao;
    if ($("#SGP_ExibirObservacoes").is(":checked")) {
      strExibirObservacoes = strSim;
    }

    $.ajax({
      url: $.trim($("#hddExtratosConsultar").val()),
      dataType: "json",
      cache: false,
      data: {
        EMP_ID: $.trim($("#EMP_ID").val()),
        CON_ID: $.trim($("#CON_ID").val()),
        DataCadastroInicial: $("#txtDataInicial").val(),
        DataCadastroFinal: $("#txtDataFinal").val(),
        DataConciliadoInicial: $("#txtDataInicial2").val(),
        DataConciliadoFinal: $("#txtDataFinal2").val(),
        SGP_ExibirObservacoes: strExibirObservacoes
      },
      type: "POST",
    }).success(function (data) {
        $(".btn-filtro").prop("disabled", false);
        $("#btnFiltrar").html(strLabel);
        preLoadingClose();

        if (data.error) {
          $("#consultar-dados").html('');
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $("#consultar-dados").html(data.strHtml);
  
      }).fail(function (data) {
        $(".btn-filtro").prop("disabled", false);
        $("#btnFiltrar").html(strLabel);

        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function confirmarFinanceiroExtratoConciliar(
  codigo,
  origem,
  strMensagem,
  strData
) {
  var strHtml =
    "<form class='form-horizontal' target='_blank' id='frmFormularioDialog' method='post' action='" +
    $.trim($("#hddContratosImpressao").val()) +
    "/" +
    $.trim($("#CON_ID").val()) +
    "' enctype='multipart/form-data'>";
  strHtml +=
    "Data Conciliado: <input id='MOV_DataConciliado' name='MOV_DataConciliado' value='" +
    strData +
    "' type='date' class='form-control input-md'>";
  strHtml += "</form>";

  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem + strHtml,
    size: BootstrapDialog.SIZE_WIDE,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: "btn-primary",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          if ($.trim($("#MOV_DataConciliado").val()) == "") {
            $.notify("Data conciliado precisa ser informado.", "error");
            return;
          } else {
            preLoadingOpen();

            $.post(
              $.trim($("#hddFinanceiroExtratosConciliar").val()),
              {
                ID: $.trim(codigo),
                Origem: $.trim(origem),
                MOV_DataConciliado: $.trim($("#MOV_DataConciliado").val()),
              },
              function (data2) {
                //alert(data2); return;
                if (data2.sucesso == "true") {
                  consultarExtratos();
                  $.notify(data2.mensagem, "success");
                } else {
                  $.notify(data2.mensagem, "error");
                }

                $(".modal").modal("hide");
                preLoadingClose();
                return;
              },
              "json"
            );
          }
        },
      },
    ],
  });
}

function confirmarFinanceiroExtratoDesconciliar(codigo, origem, strMensagem) {
  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem,
    size: BootstrapDialog.SIZE_WIDE,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: "btn-primary",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          preLoadingOpen();

          $.post(
            $.trim($("#hddFinanceiroExtratosDesconciliar").val()),
            {
              ID: $.trim(codigo),
              Origem: $.trim(origem),
            },
            function (data2) {
              //alert(data2); return;
              if (data2.sucesso == "true") {
                consultarExtratos();
                $.notify(data2.mensagem, "success");
              } else {
                $.notify(data2.mensagem, "error");
              }

              $(".modal").modal("hide");
              preLoadingClose();
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function cancelarTituloCompras(documentoID, strMensagem) {
  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_DANGER,
    size: BootstrapDialog.SIZE_WIDE,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: "btn-danger",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          preLoadingOpen();

          $.post(
            $.trim($("#hddDocumentosCancelarTituloFinanceiro").val()),
            {
              DOC_ID: $.trim(documentoID),
            },
            function (data2) {
              //alert(data2); return;
              if (data2.sucesso == "true") {
                $("#btnGerarTitulo").show();
                $("#btnCancelarTitulo").hide();

                $.notify(data2.mensagem, "success");
              } else {
                $.notify(data2.mensagem, "warn");
              }

              $(".modal").modal("hide");
              preLoadingClose();

              setTimeout(function () {
                redir("", "parent");
              }, 1500);
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function salvarCarteirasAditivos() {
  if ($.trim($("#CTO_ID").val()) == "") {
    $.notify("Contrato precisa ser selecionado.", "warn");
    return;
  } else {
    //Verifica as informações da Condição do Contrato
    var arrQuantidades = new Array();
    var arrIndexadores = new Array();
    var arrIndexadores2 = new Array();
    var arrCorrecoes = new Array();
    var arrJuros = new Array();
    var arrMesInicio = new Array();
    var arrPercentuais = new Array();

    $("input[name='CSE_QuantidadeParcelas[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrQuantidades.push($(this).val());
      }
    });

    $("select[name='IND_ID[]'] option:selected").each(function () {
      if ($.trim($(this).val()) != "") {
        arrIndexadores.push($(this).val());
      }
    });

    $("select[name='IND_ID2[]'] option:selected").each(function () {
      if ($.trim($(this).val()) != "") {
        arrIndexadores2.push($(this).val());
      }
    });

    $("select[name='SEL_SimNao2[]'] option:selected").each(function () {
      if ($.trim($(this).val()) != "") {
        arrCorrecoes.push($(this).val());
      }
    });

    $("select[name='SEL_SimNao3[]'] option:selected").each(function () {
      if ($.trim($(this).val()) != "") {
        arrJuros.push($(this).val());
      }
    });

    $("input[type=date][name='CSE_PeriodoInicio[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrMesInicio.push($(this).val());
      }
    });

    $("input[type=text][name='CSE_PercentualSerie[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrPercentuais.push($(this).val());
      }
    });

    //Valores OK
    if (arrCorrecoes.length == arrJuros.length && arrPercentuais.length == arrMesInicio.length && arrQuantidades.length == arrCorrecoes.length) {
      var strLabel = consultarPadraoInicial(false);

      $.ajax({
        url: $.trim($("#hddCarteirasAditivosSalvar").val()),
        dataType: "json",
        cache: false,
        type: "POST",
        data: {
          CTO_ID: $.trim($("#CTO_ID").val()),
          CAD_Obs: $.trim($("#CAD_Obs").val()),
          arrQuantidades: arrQuantidades,
          arrIndexadores: arrIndexadores,
          arrIndexadores2: arrIndexadores2,
          arrCorrecoes: arrCorrecoes,
          arrJuros: arrJuros,
          arrMesInicio: arrMesInicio,
          arrPercentuais: arrPercentuais,
        },
      }).success(function (data) {
        consultarPadraoSucesso(strLabel, false);

        if (data.error) {
          consultarPadraoExcessao();
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        $("#btnCancelarCarteirasContratos").trigger("click");
        $(".campoFormulario").val("");

      }).fail(function (data) {
        consultarPadraoFalha(strLabel, false);
        dialogAlert(strAtencao, data.responseText, 6);
      });
    } else {
      $.notify("Verifique se todos os campos da condição do contrato estão preenchidos corretamente.", "error");
    }
  }
}

function consultarCarteiraAditivos() {
  var strLabel = consultarPadraoInicial();
  var arrContratos = new Array();
  var arrIndexadores = new Array();

  $("select[name='CTO_ID[]'] option:selected").each(function () {
    if ($.trim($(this).val()) != "") {
      arrContratos.push($(this).val());
    }
  });

  $("select[name='IND_ID[]'] option:selected").each(function () {
    if ($.trim($(this).val()) != "") {
      arrIndexadores.push($(this).val());
    }
  });

  $.ajax({
    url: $.trim($("#carteiras_aditivos_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      CTO_ID: arrContratos,
      IND_ID: arrIndexadores,
      CAD_DataHoraCadastroInicial: $.trim($("#txtDataInicial").val()),
      CAD_DataHoraCadastroFinal: $.trim($("#txtDataFinal").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);

      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function formularioCarteirasRenegociacao() {
  var arrSelecionados = new Array();
  $("input[type=checkbox][name='items[]']:checked").each(function () {
    arrSelecionados.push($(this).val());
  });

  if (arrSelecionados.length > 0) {
    $(".btn-formulario").prop("disabled", true);

    $.ajax({
      url: $.trim($("#hddCarteirasRenegociacaoConfirmar").val()),
      dataType: "json",
      cache: false,
      data: {
        CTO_ID: $.trim($("#CTO_ID").val()),
        CTO_Numero: $.trim($("#CTO_Numero").val()),
        CTP_ID: arrSelecionados,
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        BootstrapDialog.show({
          title: data.strTitulo,
          message: data.strHtml,
          size: BootstrapDialog.SIZE_WIDE,
          buttons: [
            {
              label: strLabelNao,
              cssClass: "btn-danger btn-formulario",
              action: function (dialogItself) {
                dialogItself.close();
              },
            },
            {
              label: strLabelSim,
              cssClass: "btn-primary btn-formulario",
              data: {
                js: "btn-confirm",
                "user-id": "3",
              },
              id: "btn-confirmar-sim",
              action: function () {
                //Verifica se todos os campos estão preenchidos corretamente
                if ($.trim($("#IND_ID").val()) == "") {
                  $.notify("Indexador Pré precisa ser informado.", "warn");
                  return;
                } else if ($.trim($("#IND_ID_PosDataEntrega").val()) == "") {
                  $.notify("Indexador Pós precisa ser informado.", "warn");
                  return;
                } else {
                  $(".btn-formulario").prop("disabled", true);
                  var strLabel = $("#btn-confirmar-sim").html();
                  $("#btn-confirmar-sim").html(strCarregando);

                  var arrVencimentos = new Array();
                  var arrValores = new Array();
                  var arrPeriodicidades = new Array();
                  var intTotalParcelas = parseInt(
                    $("#CTP_QuantidadeParcelas").val()
                  );

                  $("input[type=date][name='CTP_DataVencimento[]']").each(
                    function () {
                      if ($.trim($(this).val()) != "") {
                        arrVencimentos.push($(this).val());
                      }
                    }
                  );

                  $("input[type=text][name='CTP_ValorParcelaAtual[]']").each(
                    function () {
                      if (
                        $.trim($(this).val()) != "" &&
                        parseFloat($(this).val()) > 0
                      ) {
                        arrValores.push($(this).val());
                      }
                    }
                  );

                  $("select[name='CSE_Periodicidade[]'] option:selected").each(
                    function () {
                      if ($.trim($(this).val()) != "") {
                        arrPeriodicidades.push($(this).text());
                      }
                    }
                  );

                  if (
                    intTotalParcelas == arrVencimentos.length &&
                    intTotalParcelas == arrValores.length
                  ) {
                    var arrParcelasSelecionadas = new Array();
                    $("input[type=checkbox][name='items[]']:checked").each(
                      function () {
                        arrParcelasSelecionadas.push($(this).val());
                      }
                    );

                    $.ajax({
                      url: $.trim(
                        $("#hddCarteirasRenegociacaoRenegociar").val()
                      ),
                      dataType: "json",
                      cache: false,
                      data: {
                        CTO_ID: $.trim($("#CTO_ID").val()),
                        IND_ID: $.trim($("#IND_ID").val()),
                        IND_ID_PosDataEntrega: $.trim(
                          $("#IND_ID_PosDataEntrega").val()
                        ),
                        CTP_SaldoDevedor: $.trim($("#CTP_SaldoDevedor").val()),
                        CTP_SaldoCalculado: $.trim(
                          $("#CTP_SaldoCalculado").val()
                        ),
                        arrVencimentos: arrVencimentos,
                        arrValores: arrValores,
                        arrParcelasSelecionadas: arrParcelasSelecionadas,
                        SGP_Periodicidade: arrPeriodicidades,
                      },
                      type: "POST",
                    })
                      .success(function (data) {
                        $(".btn-formulario").prop("disabled", false);
                        $("#btn-confirmar-sim").html(strLabel);

                        if (data.error) {
                          dialogAlert(strInformacao, data.error.msg, 6);
                          return;
                        }

                        $(".modal").modal("hide");
                        $.notify(data.mensagem, "success");
                        consultarCarteiraContratosParcelasPorContrato();
                      })
                      .fail(function (data) {
                        $(".btn-formulario").prop("disabled", false);
                        $("#btn-confirmar-sim").html(strLabel);

                        dialogAlert(strAtencao, data.responseText, 6);
                      });
                  } else {
                    $(".btn-formulario").prop("disabled", false);
                    $("#btn-confirmar-sim").html(strLabel);

                    $.notify(
                      "Todos os campos devem ser preenchidos corretamente.",
                      "warn"
                    );
                  }
                }
              },
            },
          ],
        });
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        dialogAlert(strAtencao, data.responseText, 6);
      });

      setTimeout(function () {
        $('#IND_ID, #IND_ID_PosDataEntrega').chosen();
      }, 1500);
      
  } else {
    preLoadingClose();
    $(".btn-formulario").prop("disabled", false);
    $.notify(
      "Selecione no minímo uma opção para fazer a renegociação.",
      "warn"
    );
  }
}

function carregaCarteirasRenegociacaoParcelas() {
  if (
    $.trim($("#CTP_QuantidadeParcelas").val()) == "" ||
    $.trim($("#CTP_QuantidadeParcelas").val()) == "0,00"
  ) {
    $.notify("Quantidade de parcelas precisa ser maior que zero.", "warn");
    return;
  } else {
    $("#cntCarteirasRenagociacaoParcelas").html(strCarregando);
    $("#CTP_SaldoCalculado").val($("#CTP_SaldoDevedor").val());

    $.ajax({
      url: $.trim($("#hddCarteirasRenegociacaoParcelas").val()),
      dataType: "json",
      cache: false,
      data: {
        CTP_QuantidadeParcelas: $.trim($("#CTP_QuantidadeParcelas").val()),
        CTP_SaldoDevedor: $.trim($("#CTP_SaldoDevedor").val()),
        SGP_DataVencimentoInicial: $.trim(
          $("#SGP_DataVencimentoInicial").val()
        ),
      },
      type: "POST",
    })
      .success(function (data) {
        if (data.error) {
          $("#cntCarteirasRenagociacaoParcelas").html("");
          $("#CTP_SaldoCalculado").val("");

          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $("#cntCarteirasRenagociacaoParcelas").html(data.strHtml);

        $("input[type=text][name='CTP_ValorParcelaAtual[]']").blur(function () {
          var arrValores = new Array();
          $("input[type=text][name='CTP_ValorParcelaAtual[]']").each(
            function () {
              if (
                $.trim($(this).val()) != "" &&
                parseFloat($(this).val()) > 0
              ) {
                arrValores.push($(this).val());
              }
            }
          );

          $.post(
            $.trim($("#hddCarteirasRenegociacaoCalcular").val()),
            {
              arrValores: arrValores,
            },
            function (data2) {
              //alert(data2); return;
              if (data2.sucesso == "true") {
                $("#CTP_SaldoCalculado").val(data2.douValorCalculado);
              } else {
                $.notify(data2.mensagem, "warn");
              }
            },
            "json"
          );
        });

        setInitFunctions();
      })
      .fail(function (data) {
        $("#cntCarteirasRenagociacaoParcelas").html("");
        $("#CTP_SaldoCalculado").val("");

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function atualizaFinanceiroParcelaVencimento(parcelaID, dataVencimento) {
  $(".btn-formulario").prop("disabled", true);

  $.ajax({
    url: $.trim($("#contas_pagar_parcelas_vencimento").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_ID: $.trim($("#CPG_ID").val()),
      CPP_ID: $.trim(parcelaID),
      CPP_DataVencimento: dataVencimento,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $.notify(data.mensagem, "success");

      setTimeout(function () {
        redir("", "parent");
      }, 1000);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function atualizaFinanceiroParcelaValor(intCodigo, douValor) {
  $(".btn-formulario").prop("disabled", true);

  $.ajax({
    url: $.trim($("#contas_pagar_parcelas_valor").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_ID: $.trim($("#CPG_ID").val()),
      CPP_ID: $.trim(intCodigo),
      CPP_Valor: $.trim(douValor),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#tdValorTotal").html(data.douValorTotal);
      $.notify(data.mensagem, "success");

      setTimeout(function () {
        redir("", "parent");
      }, 1000);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function detalhesCarteiraAditivos(aditivoID, contratoID) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddCarteiraAditivosDetalhes").val()),
    {
      CTO_ID: $.trim(contratoID),
      CAD_ID: $.trim(aditivoID),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(data.strTitulo, data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "warn");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function consultarComprasDocumentosAnexos() {
  preLoadingOpen();
  $("#consultar-dados").html(strCarregando);

  $.post(
    $.trim($("#hddComprasDocumentosConsultarAnexos").val()),
    {
      DOC_ID: $.trim($("#DOC_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#consultar-dados").html(data.strHtml);
        setInitFunctions();
      } else {
        $("#consultar-dados").html("");
        $.notify(data.mensagem, "warn");
      }
      preLoadingClose();
    },
    "json"
  );
}

function consultarFinanceiroContasPagarAnexos() {
  preLoadingOpen();
  $("#consultar-anexos").html(strCarregando);

  $.post(
    $.trim($("#hddFinanceiroContasPagarConsultarAnexos").val()),
    {
      CPG_ID: $.trim($("#CPG_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#consultar-anexos").html(data.strHtml);
      } else {
        $("#consultar-anexos").html("");
        $.notify(data.mensagem, "warn");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function consultarCarteiraContratosAnexos() {
  preLoadingOpen();
  $("#consultar-anexos").html(strCarregando);

  $.post(
    $.trim($("#hddCarteirasContratosConsultarAnexos").val()),
    {
      CTO_ID: $.trim($("#CTO_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#consultar-anexos").html(data.strHtml);
      } else {
        $("#consultar-anexos").html("");
        $.notify(data.mensagem, "warn");
      }
      preLoadingClose();
    },
    "json"
  );
}

function enterPesquisarCarteiraContratosAgentes(e) {
  if (e.keyCode == 13) {
    consultarCateirasContratosAgentes();
  }
}

function consultarCarteiraContratosCompradores() {
  preLoadingOpen();
  $("#consultar-entidades").html(strCarregando);

  $.post(
    $.trim($("#carteiras_contratos_consultar_compradores").val()),
    {
      ENT_Pesquisar: $.trim($("#ENT_Pesquisar").val()),
      CTO_ID: $.trim($("#CTO_ID").val()),
    },
    function (data) {
      //alert(data); return;
      $("#consultar-entidades").html(data.strHtml);

      /* if (data.sucesso == 'true'){
      //dialogAlert2('teste', strCarregando, 3);

    }else{
      $('#consultar-entidades').html(data,str);
      //$.notify(data.mensagem, "error");
    } */

      preLoadingClose();
      return;
    },
    "json"
  );
}

function filtrarCateirasContratosAgentes() {
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#carteiras_contratos_consultar_filtrar").val()),
    dataType: "json",
    cache: false,
    data: {
      CTO_ID: $.trim($("#CTO_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.statusText + " (" + data.status + ")", 6);
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3);

      setTimeout(function () {
        $("#ENT_Pesquisar").focus();
      }, 1000);
    })
    .fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarCateirasContratosAgentes() {
  $("#dados-agentes").html(strCarregando);

  $.ajax({
    url: $.trim($("#carteiras_contratos_consultar_agentes").val()),
    dataType: "json",
    cache: false,
    data: {
      ENT_Pesquisar: $.trim($("#ENT_Pesquisar").val()),
      CTO_ID: $.trim($("#CTO_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      if (data.error) {
        $("#dados-agentes").html("");
        dialogAlert(strAtencao, data.statusText + " (" + data.status + ")", 6);
        return;
      }

      $("#dados-agentes").html(data.strHtml);
      return;
    })
    .fail(function (data) {
      //alert(data); return;
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function adicionarCarteiraContratosNovoAgente() {
  $("#btnFormularioNovoCliente").trigger("click");

  setTimeout(function () {
    $("#strAcao").val($("#hddAgenteComprador").val());
  }, 1000);
}

function adicionarCarteiraContratosAgentes(entidadeID) {
  var arrEntidades = new Array();

  if ($.trim(entidadeID) != "") {
    arrEntidades.push($.trim(entidadeID));
  } else {
    $("input[type=checkbox][name='ENT_ID2[]']:checked").each(function () {
      arrEntidades.push($(this).val());
    });
  }

  if (arrEntidades.length == 0) {
    $.notify("Seleciona no minímo 1 (UM) agente para adicionar.", "warn");
    return;
  } else {
    $("button, input").prop("disabled", true);

    $.ajax({
      url: $.trim($("#carteiras_contratos_salvar_compradores").val()),
      dataType: "json",
      cache: false,
      data: {
        ENT_ID: arrEntidades,
        CTO_ID: $.trim($("#CTO_ID").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        //alert(data); return;
        $("button, input").prop("disabled", false);
        if (data.error) {
          $("#dados-agentes").html("");
          dialogAlert(
            strAtencao,
            data.statusText + " (" + data.status + ")",
            6
          );
          return;
        }

        consultarCateirasContratosAgentes();
        consultarCarteiraContratosCompradores();
        return;
      })
      .fail(function (data) {
        //alert(data); return;
        $("button, input").prop("disabled", false);
        dialogAlert(strAtencao, data.responseText, 6);
        return;
      });
  }
}

function consultarEmpreendimentosEstruturasAnexos() {
  preLoadingOpen();
  $("#consultar-anexos").html(strCarregando);

  $.post(
    $.trim($("#hddEmpreendimentosEstruturasConsultarAnexos").val()),
    {
      EST_ID: $.trim($("#EST_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#consultar-anexos").html(data.strHtml);
      } else {
        $("#consultar-anexos").html("");
        $.notify(data.mensagem, "warn");
      }
      preLoadingClose();
    },
    "json"
  );
}

function gerarCarteiraContratos(
  strMensagem,
  arrIndexadores,
  arrCorrecoes,
  strDataProposta
) {
  strHtml =
    "<br><br>Indexador: <select id='IND_ID' name='IND_ID' class='form-control'>";
  strHtml += "<option value=''>" + strSelecione + "</option>";

  if (arrIndexadores.length > 0) {
    for (var i = 0; i < arrIndexadores.length; i++) {
      var strSelected = "";
      if (arrIndexadores.length == 1) {
        strSelected = "selected";
      }

      strHtml +=
        "<option " +
        strSelected +
        " value='" +
        arrIndexadores[i].IND_ID +
        "'>" +
        arrIndexadores[i].IND_Codigo +
        " - " +
        arrIndexadores[i].IND_Descricao +
        "</option>";
    }
  }

  strHtml += "</select>";
  strHtml +=
    "<br>Indexador Pós: <select id='IND_ID2' name='IND_ID2' class='form-control'>";
  strHtml += "<option value=''>" + strSelecione + "</option>";

  if (arrIndexadores.length > 0) {
    for (var i = 0; i < arrIndexadores.length; i++) {
      var strSelected = "";
      if (arrIndexadores.length == 1) {
        strSelected = "selected";
      }

      strHtml +=
        "<option " +
        strSelected +
        " value='" +
        arrIndexadores[i].IND_ID +
        "'>" +
        arrIndexadores[i].IND_Codigo +
        " - " +
        arrIndexadores[i].IND_Descricao +
        "</option>";
    }
  }

  strHtml += "</select>";
  strHtml +=
    "<br>Periodicidade Correção: <select id='CTO_PeriodicidadeCorrecao' name='CTO_PeriodicidadeCorrecao' class='form-control'>";
  strHtml += "<option value=''>" + strSelecione + "</option>";

  for (var key in arrCorrecoes) {
    var strSelected = "";
    if (arrCorrecoes.length == 1) {
      strSelected = "selected";
    }

    strHtml +=
      "<option " +
      strSelected +
      " value='" +
      key +
      "'>" +
      arrCorrecoes[key] +
      "</option>";
  }

  strHtml += "</select>";
  strHtml +=
    "<br>Data Emissão: <input id='PRO_DataEmissao' name='PRO_DataEmissao' value='" +
    strDataProposta +
    "' autocomplete='off' type='date' class='form-control input-md'>";
  strHtml +=
    "<br>Data Base: <input id='PRO_DataBase' name='PRO_DataBase' value='" +
    strDataProposta +
    "' autocomplete='off' type='date' class='form-control input-md'>";
  strMensagem += strHtml;

  BootstrapDialog.show({
    title: strInformacao,
    id: "dialogSGPGerarCarteira",
    message: strMensagem,
    size: BootstrapDialog.SIZE_WIDE,
    type: BootstrapDialog.TYPE_SUCCESS,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        id: "btnSGPProcessarSIM",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          if ($.trim($("#IND_ID").val()) == "") {
            $.notify("Indexador precisa ser informado.", "warn");
            return;
          } else if ($.trim($("#IND_ID2").val()) == "") {
            $.notify("Indexador Pós precisa ser informado.", "warn");
            return;
          } else if ($.trim($("#CTO_PeriodicidadeCorrecao").val()) == "") {
            $.notify(
              "Periodicidade da correção precisa ser informada.",
              "warn"
            );
            return;
          } else if ($.trim($("#PRO_DataEmissao").val()) == "") {
            $.notify("Data emissão precisa ser informada.", "warn");
            return;
          } else if ($.trim($("#PRO_DataBase").val()) == "") {
            $.notify("Data base precisa ser informada.", "warn");
            return;
          } else {
            preLoadingOpen();
            $("button").prop("disabled", true);

            $.ajax({
              url: $.trim($("#hddComercialPropostaGerarContrato").val()),
              dataType: "json",
              cache: false,
              data: {
                PRO_ID: $.trim($("#PRO_ID").val()),
                IND_ID: $.trim($("#IND_ID").val()),
                IND_ID2: $.trim($("#IND_ID2").val()),
                CTO_PeriodicidadeCorrecao: $.trim(
                  $("#CTO_PeriodicidadeCorrecao").val()
                ),
                PRO_DataEmissao: $.trim($("#PRO_DataEmissao").val()),
                PRO_DataBase: $.trim($("#PRO_DataBase").val()),
              },
              type: "POST",
            })
              .success(function (data) {
                //alert(data); return;
                preLoadingClose();
                $("button").prop("disabled", false);

                if (data.error) {
                  dialogAlert(strAtencao, data.error.msg, 6);
                  return;
                }

                $.notify(data.mensagem, "success");

                setTimeout(function () {
                  redir(data.redir, "parent");
                }, 1000);
                return;
              })
              .fail(function (data) {
                //alert(data); return;
                $("button").prop("disabled", false);
                preLoadingClose();
                dialogAlert(strAtencao, data.responseText, 6);
                return;
              });
          }
        },
      },
    ],
  });

  setTimeout(function () {
    $(".modal-header").css("background-color", "#9896c7");
    $("#btnSGPProcessarSIM").css("background-color", "#9896c7");
  }, 200);
}

function cancelarCarteiraContrato(propostaID, strMensagem) {
  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_DANGER,
    size: BootstrapDialog.SIZE_WIDE,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: "btn-danger",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          preLoadingOpen();

          $.post(
            $.trim($("#hddCarteiraCancelarContratoProposta").val()),
            {
              PRO_ID: $.trim(propostaID),
            },
            function (data2) {
              //alert(data2); return;
              if (data2.sucesso == "true") {
                $.notify(data2.mensagem, "success");
              } else {
                $.notify(data2.mensagem, "error");
              }
              $(".modal").modal("hide");
              preLoadingClose();
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function confirmarAprovacaoViabilidades(viabilidadeID, strMensagem) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_PRIMARY,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-primary",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddViabilidadesAprovar").val()),
          {
            VIA_ID: $.trim(viabilidadeID),
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              $.notify(data.mensagem, "success");

              setTimeout(function () {
                preLoadingClose();
                redir("", "parent");
              }, 500);
            } else {
              $.notify(data.mensagem, "error");
            }
            preLoadingClose();
            return;
          },
          "json"
        );
      }
    },
  });
}

function confirmarReprovacaoViabilidades(viabilidadeID, strMensagem) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_DANGER,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-danger",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddViabilidadesReprovar").val()),
          {
            VIA_ID: $.trim(viabilidadeID),
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              $.notify(data.mensagem, "success");

              setTimeout(function () {
                preLoadingClose();
                redir("", "parent");
              }, 500);
            } else {
              $.notify(data.mensagem, "error");
            }
            preLoadingClose();
            return;
          },
          "json"
        );
      }
    },
  });
}

function adicionarImpostosContasPagar() {
  preLoadingOpen();
  $("#tab_impostos").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddFinanceiroContasPagarImpostosAdicionar").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      CPG_ID: $.trim($("#CPG_ID").val()),
      EMP_Dados: $.trim($("#EMP_Dados").val()),
    },
  })
    .success(function (data) {
      if (data.error) {
        $("#tab_impostos").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#tab_impostos").html(data.strHtml);
      
      calcularContasPagarValorIncidencia($('#CPG_Valor').val(), 100)

      setTimeout(function () {
        $("#CPI_TipoCalculo").change(function (e) {
          $(
            "#CPI_ValorImposto, #btnSalvarImpostos, #CPI_PercentualImposto"
          ).prop("disabled", true);
          $("#CPP_Imposto_0, #CPI_ValorImposto, #CPI_PercentualImposto").val(
            ""
          );

          if ($.trim(this.value) != "") {
            if ($.trim(this.value) == "V") {
              $("#CPI_ValorImposto").prop("disabled", false);
            } else {
              $("#CPI_PercentualImposto").prop("disabled", false);
            }
          }
        });

        $("#CPI_PercentualImposto").blur(function (e) {
          $("#btnSalvarImpostos").prop("disabled", true);

          $.ajax({
            url: $.trim($("#contas_pagar_impostos_calcular_tipo").val()),
            dataType: "json",
            cache: false,
            data: {
              CPI_TipoCalculo: $.trim($("#CPI_TipoCalculo").val()),
              CPI_ValorIncidencia: $.trim($("#CPI_ValorIncidencia").val()),
              CPI_PercentualImposto: $.trim(this.value),
            },
            type: "POST",
          })
            .success(function (data) {
              if (data.error) {
                dialogAlert(strInformacao, data.error.msg, 6);
                return;
              }

              $("#btnSalvarImpostos").prop("disabled", false);
            })
            .fail(function (data) {
              dialogAlert(strAtencao, data.responseText, 6);
            });
        });

        $("#CPI_ValorImposto").blur(function (e) {
          $("#btnSalvarImpostos").prop("disabled", true);

          $.ajax({
            url: $.trim($("#contas_pagar_impostos_calcular_tipo").val()),
            dataType: "json",
            cache: false,
            data: {
              CPI_TipoCalculo: $.trim($("#CPI_TipoCalculo").val()),
              CPI_ValorIncidencia: $.trim($("#CPI_ValorIncidencia").val()),
              CPI_ValorImposto: $.trim(this.value),
            },
            type: "POST",
          })
            .success(function (data) {
              if (data.error) {
                dialogAlert(strInformacao, data.error.msg, 6);
                return;
              }

              $("#CPI_PercentualImposto").val(data.douCalculo);
              $("#CPP_Imposto_0").val($("#CPI_ValorImposto").val());
              $("#btnSalvarImpostos").prop("disabled", false);
            })
            .fail(function (data) {
              dialogAlert(strAtencao, data.responseText, 6);
            });
        });

        $("#CPI_TipoImposto, #CPI_TipoCalculo").chosen();
        consultarImpostosContasPagar();
        setInitFunctions();
        preLoadingClose();
      }, 1000);
    })
    .fail(function (data) {
      $("#tab_impostos").html("");
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarImpostosContasPagar() {
  $("#cntConsultaImpostosContasPagar").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddFinanceiroContasPagarImpostosConsultar").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_ID: $.trim($("#CPG_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        $("#cntConsultaImpostosContasPagar").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#cntConsultaImpostosContasPagar").html(data.strHtml);
    })
    .fail(function (data) {
      $("#cntConsultaImpostosContasPagar").html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarImpostosContasPagar() {
  if ($.trim($("#CPI_TipoImposto").val()) == "") {
    $.notify("Tipo do imposto precisa ser selecionado.", "warn");
    return;
  } else if($.trim($("#CPI_PercentualIncidencia").val()) == "0,00" || $.trim($("#CPI_PercentualIncidencia").val()) == null){

    $.notify("Percentual de incidência precisa ser maior que zero", "warn");
    return;
  }else{
    $("#btnSalvarImpostos").prop("disabled", true);
    preLoadingOpen();

    var arrValores = new Array();
    var arrParcelas = new Array();

    $("input[type=text][name='CPP_Imposto[]']").each(function () {
      arrValores.push($(this).val());
    });

    $("input[type=hidden][name='CPP_ID_Sequencial[]']").each(function () {
      arrParcelas.push($(this).val());
    });

    $.ajax({
      url: $.trim($("#hddFinanceiroContasPagarImpostosSalvar").val()),
      dataType: "json",
      cache: false,
      data: {
        CPG_ID: $.trim($("#CPG_ID").val()),
        CPI_TipoImposto: $.trim($("#CPI_TipoImposto").val()),
        CPI_PercentualIncidencia: $.trim($("#CPI_PercentualIncidencia").val()),
        CPI_ValorIncidencia: $.trim($("#CPI_ValorIncidencia").val()),
        CPI_PercentualImposto: $.trim($("#CPI_PercentualImposto").val()),
        CPI_ValorImposto: $.trim($("#CPI_ValorImposto").val()),
        arrValores: arrValores,
        arrParcelas: arrParcelas,
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarImpostos").prop("disabled", false);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");
        limparImpostosContasPagar();
        adicionarImpostosContasPagar();
      })
      .fail(function (data) {
        $("#btnSalvarImpostos").prop("disabled", false);
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function editarImpostosContasPagar(impostoID) {
  preLoadingOpen();

  var arrParcelas = new Array();

  $("input[type=hidden][name='CPP_ID_Sequencial[]']").each(function () {
    arrParcelas.push($(this).val());
  });

  $.post(
    $.trim($("#hddFinanceiroContasPagarImpostosEditar").val()),
    {
      CPG_ID: $.trim($("#CPG_ID").val()),
      CPI_ID: $.trim(impostoID),
      CPP_ID: arrParcelas,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#CPI_ID").val(data.arrDados[0].CPI_ID);
        $("#CPI_TipoImposto").val(data.arrDados[0].CPI_TipoImposto);
        $("#CPI_TipoImposto").trigger("chosen:updated");
        $("#CPI_TipoCalculo").val(data.arrDados[0].CPI_TipoCalculo);
        $("#CPI_TipoCalculo").trigger("chosen:updated");
        $("#CPI_TipoCalculo").trigger("change");

        if (data.arrDados[0].CPI_TipoCalculo == "V") {
          $("#CPI_ValorImposto").trigger("change");
        } else {
          $("#CPI_PercentualImposto").trigger("change");
        }

        $("#CPI_PercentualIncidencia").val(
          data.arrDados[0].CPI_PercentualIncidencia
        );
        $("#CPI_ValorIncidencia").val(data.arrDados[0].CPI_ValorIncidencia);
        $("#CPI_PercentualImposto").val(data.arrDados[0].CPI_PercentualImposto);
        $("#CPI_ValorImposto").val(data.arrDados[0].CPI_ValorImposto);

        if (data.arrParcelas.length > 0) {
          var intI = 0;
          $("input[type=text][name='CPP_Imposto[]']").each(function () {
            $(this).val(data.arrParcelas[intI].CPP_Imposto);

            intI++;
          });
        }

        $("#btnSalvarImpostos").prop("disabled", false);
      } else {
        $.notify(data.mensagem, "error");
      }

      preLoadingClose();
      return;
    },
    "json"
  );
}

function limparImpostosContasPagar() {
  $("#CPI_ID").val("");
  $("#CPI_PercentualIncidencia").val("");
  $("#CPI_ValorIncidencia").val("");
  $("#CPI_PercentualImposto").val("");
  $("#CPI_ValorImposto").val("");
  $("#CPI_TipoImposto, #CPI_TipoCalculo").val("");
  $("#CPI_TipoImposto, #CPI_TipoCalculo").trigger("chosen:updated");
  $('input[name="CPP_Imposto[]"]').val("");
  $("#btnSalvarImpostos").prop("disabled", true);
}

function confirmarExcluirImpostosContasPagar(impostoID, strMensagem) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_DANGER,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-danger",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddFinanceiroContasPagarImpostosExcluir").val()),
          {
            CPG_ID: $.trim($("#CPG_ID").val()),
            CPI_TipoImposto: $.trim(impostoID),
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              adicionarImpostosContasPagar();
              $.notify(data.mensagem, "success");
            } else {
              $.notify(data.mensagem, "error");
            }
            preLoadingClose();
            return;
          },
          "json"
        );
      }
    },
  });
}

function propostaSelecionarCorretor() {
  preLoadingOpen();

  $.post(
    $.trim($("#hddPropostasCorretoresConsultar").val()),
    {
      PRO_ID: $.trim($("#PRO_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(strInformacao, data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function consultarEstruturasViabilidades() {
  preLoadingOpen();
  $("#consultar-viabilidades").html(strCarregando);

  $.post(
    $.trim($("#hddEstruturasViabilidadesConsultar").val()),
    {
      EST_ID: $.trim($("#EST_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#consultar-viabilidades").html(data.strHtml);
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function confirmarEstruturaVinculoViabilidade(
  estruturaID,
  viabilidadeID,
  strMensagem
) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-primary",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddEstruturasViabilidadesVincular").val()),
          {
            EST_ID: $.trim(estruturaID),
            VIA_ID: $.trim(viabilidadeID),
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              $.notify(data.mensagem, "success");
              consultarEstruturasViabilidades();
            } else {
              $.notify(data.mensagem, "error");
            }
            preLoadingClose();
            return;
          },
          "json"
        );
      }
    },
  });
}

function confirmarEstruturaDesvinculoViabilidade(estruturaID, strMensagem) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_DANGER,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-danger",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddEstruturasViabilidadesDesvincular").val()),
          {
            EST_ID: $.trim(estruturaID),
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              $.notify(data.mensagem, "success");
              consultarEstruturasViabilidades();
            } else {
              $.notify(data.mensagem, "error");
            }
            preLoadingClose();
            return;
          },
          "json"
        );
      }
    },
  });
}

function adicionarFinanceiroOFXMovimentos(
  contaID,
  empresaID,
  strData,
  strDescricao,
  strTipo,
  strValor
) {
  $(".btn-filtro").prop("disabled", true);
  $("#MOV_ID").val("");
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#hddFinanceiroOFXCriarMovimentos").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      CON_ID: $.trim(contaID),
      EMP_ID: $.trim(empresaID),
      strData: $.trim(strData),
      strDescricao: $.trim(strDescricao),
      strTipo: $.trim(strTipo),
      strValor: $.trim(strValor),
    },
  })
    .success(function (data) {
      $(".btn-filtro").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3);

      setTimeout(function () {
        if (data.executar != undefined) {
          eval(data.executar);
        }
      }, 1000);
    })
    .fail(function (data) {
      $(".btn-filtro").prop("disabled", false);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarFinanceiroOFXMovimentos() {
  $("#btnSalvarItemSolicitacao").prop("disabled", true);
  var strLabel = $("#btnSalvarItemSolicitacao").html();
  $("#btnSalvarItemSolicitacao").html(strCarregando);

  $.ajax({
    url: $.trim($("#hddFinanceiroOFXSalvarMovimentos").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      CON_ID: $.trim($("#CON_ID").val()),
      EMP_ID: $.trim($("#EMP_ID").val()),
      MOV_Operacao: $.trim($("#MOV_Operacao").val()),
      CAX_ID: $.trim($("#CAX_ID").val()),
      MOV_NumeroDocumento: $.trim($("#MOV_NumeroDocumento").val()),
      MOV_Data: $.trim($("#MOV_Data").val()),
      MOV_Valor: $.trim($("#MOV_Valor").val()),
      MOV_Observacoes: $.trim($("#MOV_Observacoes").val()),
    },
  })
    .success(function (data) {
      $("#btnSalvarItemSolicitacao").html(strLabel);
      $("#btnSalvarItemSolicitacao").prop("disabled", false);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $.notify(data.mensagem, "success");
      adicionarFinanceiroOFXApropriacoes(data.intCodigo);
    })
    .fail(function (data) {
      $("#btnSalvarItemSolicitacao").html(strLabel);
      $("#btnSalvarItemSolicitacao").prop("disabled", false);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function adicionarFinanceiroOFXApropriacoes(intCodigo) {
  $("#divMovimentosApropriacoes").html(strCarregando);
  $("#divOFXMovimentos").hide();

  $.ajax({
    url: $.trim($("#hddFinanceiroOFXAdicionarApropriacoes").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      MOV_ID: $.trim(intCodigo),
    },
  })
    .success(function (data) {
      if (data.error) {
        $("#divMovimentosApropriacoes").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#divMovimentosApropriacoes").html(data.strHtml);

      setTimeout(function () {
        initFinanceiroOFXApropriacoes();
      }, 700);
    })
    .fail(function (data) {
      $("#divMovimentosApropriacoes").html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarFinanceiroOFXApropriacaoMovimentacoes() {
  if ($.trim($("#CEN_ID").val()) == "") {
    $.notify("Centro de Custo precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#PLF_Conta2").val()) == "") {
    $.notify("Plano Financeiro precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SGP_Percentual").val()) == "") {
    $.notify("Percentual precisa ser informado.", "warn");
    return;
  } else {
    $("#btnSalvarMovimentacoesApropriacao").prop("disabled", true);
    var strLabel = $("#btnSalvarMovimentacoesApropriacao").html();
    $("#btnSalvarMovimentacoesApropriacao").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddFinanceiroOFXSalvarApropriacoes").val()),
      dataType: "json",
      cache: false,
      type: "POST",
      data: {
        MOV_ID: $.trim($("#MOV_ID").val()),
        CEN_ID: $.trim($("#CEN_ID").val()),
        ORC_ID: $.trim($("#ORC_ID2").val()),
        OCI_ID: $.trim($("#OCI_ID2").val()),
        PLF_Conta: $.trim($("#PLF_Conta2").val()),
        SGP_Percentual: $.trim($("#SGP_Percentual").val()),
      },
    })
      .success(function (data) {
        $("#btnSalvarMovimentacoesApropriacao").html(strLabel);
        $("#btnSalvarMovimentacoesApropriacao").prop("disabled", false);

        if (data.error) {
          $("#SGP_PercentualTotal").html("");
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SGP_Percentual").val("");
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").selectpicker("refresh");
        $("#ORC_ID2").trigger("change");
        $("#SGP_PercentualTotal").val(data.douTotal);

        consultarFinanceiroOFXApropriacoesMovimentacoes();
      })
      .fail(function (data) {
        $("#btnSalvarMovimentacoesApropriacao").html(strLabel);
        $("#btnSalvarMovimentacoesApropriacao").prop("disabled", false);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarFinanceiroOFXApropriacoesMovimentacoes() {
  $("#cntConsultaApropriacoesMovimentacoes").html(strCarregando);

  $.ajax({
    url: $.trim($("#financeiro_consultar_apropriacoes").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      MOV_ID: $.trim($("#MOV_ID").val()),
    },
  })
    .success(function (data) {
      if (data.error) {
        $("#cntConsultaApropriacoesMovimentacoes").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#cntConsultaApropriacoesMovimentacoes").html(data.strHtml);
    })
    .fail(function (data) {
      $("#cntConsultaApropriacoesMovimentacoes").html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function atualizarFinanceiroOFXConciliado(codigo, origem) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddFinanceiroOFXAtualizarConciliadoMovimentacoes").val()),
    {
      ID: codigo,
      ORIGEM: origem,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $.notify(data.mensagem, "success");
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function dialogEquipeVendasPropostas(equipeID, douValorComissao) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddComercialPropostaEquipeVendasNovo").val()),
    {
      PRO_ID: $.trim($("#PRO_ID").val()),
      douValorComissao: douValorComissao,
      equipeID: equipeID,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(
          data.strTitulo +
          " (Comissão: <span id='spanValorTotalComissao'>" +
          douValorComissao +
          "</span> | Restante: <span id='spnValorRestanteComissaoEquipeVendas'>" +
          data.valorComissaoRestante +
          "</span>)",
          data.strHtml,
          3
        );

        setTimeout(function () {
          setInitFunctions();
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
    },
    "json"
  );
}

function atualizarValorComissaoEquipeVendasPropostas(
  fluxoID,
  strData,
  douValor,
  douJuros,
  htmlSaldoTotal
) {
  if($("#" + douValor).val() == ""){
    return;
  }
 
  if (
    $.trim($("#hddValorSelecionaoComissao").val()) !=
    $.trim($("#" + douValor).val())
  ) {
    //&& parseFloat($('#'+douValor).val()) > 0
    var douSaldoAnterior = $("#" + htmlSaldoTotal).html();
    var douValorAnterior = $("#spnValorRestanteComissaoEquipeVendas").html();
    var arrValores = new Array();

    $("#" + htmlSaldoTotal).html(strCarregando);
    $("#spnValorRestanteComissaoEquipeVendas").html(strCarregando);

    $("input[type=text][name='PCF_Valor[]']").each(function () {
      arrValores.push($(this).val());
    });

    $.ajax({
      url: $.trim($("#propostas_equipe_vendas_atualizar").val()),
      dataType: "json",
      cache: false,
      data: {
        PRO_ID: $.trim($("#PRO_ID").val()),
        PRF_ID: $.trim(fluxoID),
        PRC_ID: $.trim($("#PRC_ID").val()),
        PCF_Data: $.trim(strData),
        PCF_Valor: $.trim($("#" + douValor).val()),
        PRF_ValorJuros: $.trim(douJuros),
        arrValores: arrValores,
        PRC_ValorTotalComissao: $.trim($("#spnValorTotalEquipe").html()),
        //PRC_ValorComissao: $.trim($('#spanValorTotalComissao').html())
      },
      type: "POST",
    })
      .success(function (data) {
        if (data.error) {
          $("#" + htmlSaldoTotal).html(douSaldoAnterior);
          $("#spnValorRestanteComissaoEquipeVendas").html(douValorAnterior);
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $("#" + htmlSaldoTotal).html(data.douSaldo);
        $("#spnValorRestanteComissaoEquipeVendas").html(data.douValorTotal);
      })
      .fail(function (data) {
        $("#" + htmlSaldoTotal).html(douSaldoAnterior);
        $("#spnValorRestanteComissaoEquipeVendas").html(douValorAnterior);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarLogTerrenos() {
  preLoadingOpen();

  $(
    "#divObservacoes, #divDocumentos, #divProprietarios, #divCorretores, #divTerrenosEstudos, #divTerrenosViabilidades"
  ).html("");
  $("#tab_log").html(strCarregando);

  $.post(
    $.trim($("#hddTerrenosConsultarLog").val()),
    {
      TER_ID: $.trim($("#TER_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#tab_log").html(data.strHtml);

        if (data.totalRegistros > 0) {
          requireDataTables(false, true, true, true, true, false);
        }
      } else {
        $.notify(data.mensagem, "error");
        $("#tab_log").html("");
      }
      preLoadingClose();
    },
    "json"
  );
}

function adicionarApoioTerrenosTarefasNovoItem(tarefaCodigo) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddTerrenosApoioTarefasNovoItem").val()),
    {
      ATT_ID: $.trim($("#hddCodigoSelecionado").val()),
      ATT_Codigo: $.trim(tarefaCodigo),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          setInitFunctions();
          preLoadingClose();
        }, 500);
      }
    },
    "json"
  );
}

function salvarApoioTarefasTerrenosItem() {
  if ($.trim($("#ATT_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ATT_DiasParaInicio").val()) == "") {
    $.notify("Dias para início precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ATT_DiasParaInicio").val()) == "") {
    $.notify("Dias para término precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ATT_DiasAntecedencia").val()) == "") {
    $.notify("Dias de antecedência precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#ATT_PercentualConcluido").val()) == "" ||
    $.trim($("#ATT_PercentualConcluido").val()) < 0
  ) {
    $.notify("Percentual precisa ser informado.", "warn");
    return;
  } else {
    $("#btnSalvarTarefas").prop("disabled", true);

    $.post(
      $.trim($("#hddTerrenosApoioTarefasSalvarItem").val()),
      {
        ATT_Codigo: $.trim($("#hddTarefaCodigo").val()),
        TER_ID: $.trim($("#TER_ID").val()),
        ATT_ID: $.trim($("#ATT_ID").val()),
        ATT_Descricao: $.trim($("#ATT_Descricao").val()),
        ATT_DiasParaInicio: $.trim($("#ATT_DiasParaInicio").val()),
        ATT_DiasParaFim: $.trim($("#ATT_DiasParaFim").val()),
        ATT_Observacoes: $.trim($("#ATT_Observacoes").val()),
        // ATT_DataInicio: $.trim($('#ATT_DataInicio').val()),
        // ATT_DataFim: $.trim($('#ATT_DataFim').val()),
        ATT_DiasAntecedencia: $.trim($("#ATT_DiasAntecedencia").val()),
        ATT_PercentualConcluido: $.trim($("#ATT_PercentualConcluido").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#ATT_ID").val("");
          $("#ATT_Descricao").val("");
          $("#ATT_Observacoes").val("");
          $("#ATT_DataInicio").val("");
          $("#ATT_DataFim").val("");
          $("#ATT_DiasAntecedencia").val("");
          $("#ATT_PercentualConcluido").val("");
          $.notify(data.mensagem, "success");

          setTimeout(function () {
            preLoadingClose();
            redir("", "parent");
          }, 2500);
        } else {
          $.notify(data.mensagem, "error");
          $("#btnSalvarTarefas").prop("disabled", false);
        }

        return;
      },
      "json"
    );
  }
}

function confirmarExcluirApoioTerrenosTarefa(tarefaID, strMensagem) {
  BootstrapDialog.confirm({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_DANGER,
    closable: true,
    draggable: true,
    btnCancelLabel: strLabelNao,
    btnOKLabel: strLabelSim,
    btnOKClass: "btn-danger",
    callback: function (result) {
      if (result) {
        preLoadingOpen();

        $.post(
          $.trim($("#hddTerrenosApoioTarefasExcluir").val()),
          {
            ATT_ID: $.trim(tarefaID),
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              $.notify(data.mensagem, "success");

              setTimeout(function () {
                preLoadingClose();
                redir("", "parent");
              }, 2500);
            } else {
              $.notify(data.mensagem, "error");
              preLoadingClose();
            }
            return;
          },
          "json"
        );
      }
    },
  });
}

function consultarHiperdadosEmpreendimentosIncorporadoras() {
  $(".radioSearch").multiselect(getOptions(true));
  $("#dados-incorporadoras").html(strCarregando);

  setTimeout(function () {
    // $('.selectpicker').selectpicker('refresh');
    $(".radioSearch").val("checked", false);
    $(".radioSearch").multiselect("refresh");
    $("#INC_ID_chosen").hide();
  }, 1000);

  $.post(
    $.trim($("#hddHiperdadosEmpreendimentosIncorporadorasConsultar").val()),
    {
      CHD_ID: $.trim($("#CHD_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#dados-incorporadoras").html(data.strHtml);
      } else {
        $("#dados-incorporadoras").html("");
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function consultarHiperdadosEmpreendimentosConstrutoras() {
  $(".radioSearch").multiselect(getOptions(true));
  $("#dados-construtoras").html(strCarregando);

  $.post(
    $.trim($("#hddHiperdadosEmpreendimentosConstrutorasConsultar").val()),
    {
      CHD_ID: $.trim($("#CHD_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#dados-construtoras").html(data.strHtml);
      } else {
        $("#dados-construtoras").html("");
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function consultarHiperdadosEmpreendimentosVendedoras() {
  $(".radioSearch").multiselect(getOptions(true));
  $("#dados-vendedores").html(strCarregando);

  $.post(
    $.trim($("#hddHiperdadosEmpreendimentosVendedoresConsultar").val()),
    {
      CHD_ID: $.trim($("#CHD_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#dados-vendedores").html(data.strHtml);
      } else {
        $("#dados-vendedores").html("");
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function consultarHiperdadosEmpreendimentosUnidades() {
  $("#dados-unidades").html(strCarregando);

  setTimeout(function () {
    $("#PRU_TipoUnidade, #EST_Tipo").chosen();
    $("#PRU_TipoUnidade, #EST_Tipo").trigger("chosen:updated");
  }, 500);

  $.post(
    $.trim($("#hddHiperdadosEmpreendimentosUnidadesConsultar").val()),
    {
      CHD_ID: $.trim($("#CHD_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#dados-unidades").html(data.strHtml);
        requireDataTables(true, true, true, true, true, false, false);
      } else {
        $("#dados-unidades").html("");
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function salvarHiperdadosEmpreendimentoUnidades() {
  if ($.trim($("#PRU_TipoUnidade").val()) == "") {
    $.notify("Tipo da unidade precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#EST_Tipo").val()) == "") {
    $.notify("Tipo Preço precisa ser informada.", "warn");
    return;
  } else {
    preLoadingOpen();
    $("#btnSalvarUnidade").prop("disabled", true);

    if ($("#ENU_ValorM2Unidade").prop("disabled") == true) {
      $("#ENU_ValorM2Unidade").trigger("blur");
    } else {
      $("#ENU_ValorUnidade").trigger("blur");
    }

    setTimeout(function () {
      $.post(
        $.trim($("#hddHiperdadosEmpreendimentosUnidadesSalvar").val()),
        {
          ENU_ID: $.trim($("#ENU_ID").val()),
          CHD_ID: $.trim($("#CHD_ID").val()),
          ENU_TipoUnidade: $.trim($("#PRU_TipoUnidade").val()),
          ENU_Finais: $.trim($("#ENU_Finais").val()),
          ENU_QuantidadeUnidades: $.trim($("#ENU_QuantidadeUnidades").val()),
          ENU_QuantidadeEstoque: $.trim($("#ENU_QuantidadeEstoque").val()),
          ENU_ValorUnidade: $.trim($("#ENU_ValorUnidade").val()),
          ENU_ValorM2Unidade: $.trim($("#ENU_ValorM2Unidade").val()),
          ENU_AreaPrivada: $.trim($("#ENU_AreaPrivada").val()),
          ENU_AreaTotal: $.trim($("#ENU_AreaTotal").val()),
          ENU_QuantidadeDormitorios: $.trim(
            $("#ENU_QuantidadeDormitorios").val()
          ),
          ENU_QuantidadeSuites: $.trim($("#ENU_QuantidadeSuites").val()),
          ENU_QuantidadeBanheiros: $.trim($("#ENU_QuantidadeBanheiros").val()),
          ENU_QuantidadeVagas: $.trim($("#ENU_QuantidadeVagas").val()),
          ENU_TipoPreco: $.trim($("#EST_Tipo").val()),
          ENU_UnidadeReferencia: $.trim($("#ENU_UnidadeReferencia").val()),
        },
        function (data) {
          //alert(data); return;
          if (data.sucesso == "true") {
            limparHiperdadosEmpreendimentosUnidades();
            consultarHiperdadosEmpreendimentosUnidades();
            $("#spnDataHoraUltimaAtualizacao").html(data.strDataAtual);

            $.notify(data.mensagem, "success");
          } else {
            $.notify(data.mensagem, "error");
          }
          $("#btnSalvarUnidade").prop("disabled", false);
          preLoadingClose();
        },
        "json"
      );
    }, 1000);
  }
}

function limparHiperdadosEmpreendimentosUnidades() {
  $(
    "#ENU_ID, #ENU_UnidadeReferencia, #PRU_TipoUnidade, #ENU_Finais, #ENU_QuantidadeUnidades, #ENU_QuantidadeEstoque, #ENU_ValorUnidade, #ENU_ValorM2Unidade, #ENU_AreaPrivada, #ENU_AreaTotal, #ENU_QuantidadeDormitorios, #ENU_QuantidadeSuites, #ENU_QuantidadeBanheiros, #ENU_QuantidadeVagas"
  ).val("");
}

function editarHiperdadosEmpreendimentosUnidades(id) {
  limparHiperdadosEmpreendimentosUnidades();

  $.post(
    $.trim($("#hddHiperdadosEmpreendimentosUnidadesEditar").val()),
    {
      CHD_ID: $.trim($("#CHD_ID").val()),
      ENU_ID: $.trim(id),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#ENU_ID").val(data.arrDados[0].ENU_ID);
        $("#PRU_TipoUnidade").val(data.arrDados[0].ENU_TipoUnidade);
        $("#ENU_Finais").val(data.arrDados[0].ENU_Finais);
        $("#EST_Tipo").val(data.arrDados[0].ENU_TipoPreco);
        $("#ENU_QuantidadeUnidades").val(
          data.arrDados[0].ENU_QuantidadeUnidades
        );
        $("#ENU_QuantidadeEstoque").val(data.arrDados[0].ENU_QuantidadeEstoque);
        $("#ENU_ValorUnidade").val(data.arrDados[0].ENU_ValorUnidade);
        $("#ENU_ValorM2Unidade").val(data.arrDados[0].ENU_ValorM2Unidade);
        $("#ENU_AreaPrivada").val(data.arrDados[0].ENU_AreaPrivada);
        $("#ENU_AreaTotal").val(data.arrDados[0].ENU_AreaTotal);
        $("#ENU_QuantidadeDormitorios").val(
          data.arrDados[0].ENU_QuantidadeDormitorios
        );
        $("#ENU_QuantidadeSuites").val(data.arrDados[0].ENU_QuantidadeSuites);
        $("#ENU_QuantidadeBanheiros").val(
          data.arrDados[0].ENU_QuantidadeBanheiros
        );
        $("#ENU_QuantidadeVagas").val(data.arrDados[0].ENU_QuantidadeVagas);
        $("#ENU_UnidadeReferencia").val(data.arrDados[0].ENU_UnidadeReferencia);

        if ($.trim($("#EST_Tipo").val()) == "M") {
          $("#ENU_ValorUnidade").prop("disabled", true);
        } else {
          $("#ENU_ValorUnidade").prop("disabled", false);
        }

        $("#PRU_TipoUnidade, #EST_Tipo").chosen();
        $("#PRU_TipoUnidade, #EST_Tipo").trigger("chosen:updated");
      } else {
        $.notify(data.mensagem, "error");
      }
      return;
    },
    "json"
  );
}

function confirmarHiperdadosEmpreendimentosUnidadesConferir(
  codigo,
  strMensagem
) {
  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem,
    size: BootstrapDialog.SIZE_WIDE,
    type: BootstrapDialog.TYPE_SUCCESS,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: "btn-success",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          preLoadingOpen();

          $.post(
            $.trim($("#hddHiperdadosEmpreendimentosUnidadesConferir").val()),
            {
              ENU_ID: $.trim(codigo),
            },
            function (data2) {
              //alert(data2); return;
              if (data2.sucesso == "true") {
                consultarHiperdadosEmpreendimentosUnidades();
                $.notify(data2.mensagem, "success");
              } else {
                $.notify(data2.mensagem, "error");
              }

              $(".modal").modal("hide");
              preLoadingClose();
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function confirmarHiperdadosEmpreendimentosUnidadesDesconferir(
  codigo,
  strMensagem
) {
  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem,
    type: BootstrapDialog.TYPE_DANGER,
    size: BootstrapDialog.SIZE_WIDE,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: "btn-danger",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          preLoadingOpen();

          $.post(
            $.trim($("#hddHiperdadosEmpreendimentosUnidadesConferir").val()),
            {
              ENU_ID: $.trim(codigo),
            },
            function (data2) {
              //alert(data2); return;
              if (data2.sucesso == "true") {
                consultarHiperdadosEmpreendimentosUnidades();
                $.notify(data2.mensagem, "success");
              } else {
                $.notify(data2.mensagem, "error");
              }

              $(".modal").modal("hide");
              preLoadingClose();
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function salvarHiperdadosEmpreendimentosIncorporadoras() {
  if ($.trim($("#INC_ID").val()) == "") {
    $.notify("Incorporadora precisa ser informada.", "error");
  } else {
    $("#btnSalvarIncorporadora").prop("disabled", true);
    preLoadingOpen();

    $.post(
      $.trim($("#hddHiperdadosEmpreendimentosIncorporadorasSalvar").val()),
      {
        CHD_ID: $.trim($("#CHD_ID").val()),
        INC_ID: $.trim($("#INC_ID").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");
          consultarHiperdadosEmpreendimentosIncorporadoras();
          $("#spnDataHoraUltimaAtualizacao").html(data.strDataAtual);
          $(".radioSearch").val("checked", false);
          $(".radioSearch").multiselect("refresh");
        } else {
          $.notify(data.mensagem, "error");
        }

        $("#btnSalvarIncorporadora").prop("disabled", false);
        preLoadingClose();
      },
      "json"
    );
  }
}

function salvarHiperdadosEmpreendimentosConstrutoras() {
  if ($.trim($("#CON_ID").val()) == "") {
    $.notify("Construtora precisa ser informada.", "error");
  } else {
    $("#btnSalvarConstrutora").prop("disabled", true);
    preLoadingOpen();

    $.post(
      $.trim($("#hddHiperdadosEmpreendimentosConstrutorasSalvar").val()),
      {
        CHD_ID: $.trim($("#CHD_ID").val()),
        CON_ID: $.trim($("#CON_ID").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");
          consultarHiperdadosEmpreendimentosConstrutoras();

          $("#spnDataHoraUltimaAtualizacao").html(data.strDataAtual);
          $(".radioSearch").val("checked", false);
          $(".radioSearch").multiselect("refresh");
        } else {
          $.notify(data.mensagem, "warn");
        }

        $("#btnSalvarConstrutora").prop("disabled", false);
        preLoadingClose();
        return;
      },
      "json"
    );
  }
}

function salvarHiperdadosEmpreendimentosVendedores() {
  if ($.trim($("#VEN_ID").val()) == "") {
    $.notify("Vendedor(a) precisa ser informada.", "error");
  } else {
    $("#btnSalvarVendedor").prop("disabled", true);
    preLoadingOpen();

    $.post(
      $.trim($("#hddHiperdadosEmpreendimentosVendedoresSalvar").val()),
      {
        CHD_ID: $.trim($("#CHD_ID").val()),
        VEN_ID: $.trim($("#VEN_ID").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");
          consultarHiperdadosEmpreendimentosVendedoras();

          $("#spnDataHoraUltimaAtualizacao").html(data.strDataAtual);
          $(".radioSearch").val("checked", false);
          $(".radioSearch").multiselect("refresh");
        } else {
          $.notify(data.mensagem, "warn");
        }

        $("#btnSalvarVendedor").prop("disabled", false);
        preLoadingClose();
        return;
      },
      "json"
    );
  }
}

function enterPesquisarTerrenos(e) {
  if (e.keyCode == 13) {
    consultarTerrenos();
  }
}

function consultarTerrenos() {
  var strLabel = consultarPadraoInicial();
  var arrGestores = new Array();
  var arrZonas = new Array();
  var arrStatus = new Array();
  var arrCorretores = new Array();
  var arrEstados = new Array();
  var arrCidades = new Array();
  var arrUsuarios = new Array();
  var strComite = "";

  if ($("#TER_FlagComite").is(":checked")) {
    strComite = strSim;
  }

  $("select[name='CAX_Gestor_ID[]'] option:selected").each(function () {
    arrGestores.push($(this).val());
  });

  $("select[name='CAX_Zona_ID[]'] option:selected").each(function () {
    arrZonas.push($(this).val());
  });

  $("select[name='CAX_Status_ID[]'] option:selected").each(function () {
    arrStatus.push($(this).val());
  });

  $("select[name='COR_ID[]'] option:selected").each(function () {
    arrCorretores.push($(this).val());
  });

  $("select[name='UF_ID[]'] option:selected").each(function () {
    arrEstados.push($(this).val());
  });

  $("select[name='TER_Cidade[]'] option:selected").each(function () {
    arrCidades.push($(this).val());
  });

  $("select[name='Usuario_ID[]'] option:selected").each(function () {
    arrUsuarios.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#hddTerrenosConsultar").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      SGP_Pesquisar: $.trim($("#SGP_Pesquisar").val()),
      CAX_Gestor_ID: arrGestores,
      CAX_Zona_ID: arrZonas,
      TCO_AreaMin: $.trim($("#area_min").val()),
      TCO_AreaMax: $.trim($("#area_max").val()),
      CAX_Status_ID: arrStatus,
      COR_ID: arrCorretores,
      UF_ID: arrEstados,
      TER_Cidade: arrCidades,
      TER_FlagComite: strComite,
      TER_Setor: $.trim($("#TER_Setor").val()),
      TER_Quadra: $.trim($("#TER_Quadra").val()),
      DATAINICIAL: $.trim($("#txtDataInicial").val()),
      DATAFINAL: $.trim($("#txtDataFinal").val()),
      SGP_DataCompraInicial: $.trim($("#txtDataInicialCompra").val()),
      SGP_DataCompraFinal: $.trim($("#txtDataFinalCompra").val()),
      Usuario_ID: arrUsuarios,
      TER_Responsavel: $('#TER_Responsavel').val(),
      TER_UsuResp: $('#CAX_ID').val()
    },
  }).success(function (data) {
      consultarPadraoSucesso(strLabel);

      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
      $(".selectSelectpicker").selectpicker("refresh");
    }).fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });

  /*$.post($.trim($('#hddTerrenosConsultar').val()), {
    TER_Pesquisar: $.trim($('#TER_Pesquisar').val()),
    arrGestores: arrGestores,
    arrZonas: arrZonas,
    TCO_AreaMin: $.trim($('#area_min').val()),
    TCO_AreaMax: $.trim($('#area_max').val()),
    arrStatus: arrStatus,
    arrCorretores: arrCorretores,
    UF_ID: arrEstados,
    arrCidades: arrCidades,
    strComite: strComite,
    TER_Setor: $.trim($('#TER_Setor').val()),
    TER_Quadra: $.trim($('#TER_Quadra').val()),
    TER_DataHoraCadastroInicial: $.trim($('#txtDataInicial').val()),
    TER_DataHoraCadastroFinal: $.trim($('#txtDataFinal').val()),
    SGP_DataCompraInicial: $.trim($('#txtDataInicialCompra').val()),
    SGP_DataCompraFinal: $.trim($('#txtDataFinalCompra').val())
  },
  function(data){
    //alert(data); return;
    $('#btnFiltrar').html(strLabel);
    $('.btn-filtro').prop('disabled', false);

    if (data.sucesso == 'true'){
      $('#consultar-dados').html(data.strHtml);

      if (data.totalRegistros > 0){
        requireDataTables(false, true, true, true, true, false, true);
      }

      $("#spnTotalRegistrosConsultar").html(data.totalRegistros);
    }else{
      $('#consultar-dados, #spnTotalRegistrosConsultar').html('');
      $.notify(data.mensagem, "warn");
    }
    preLoadingClose();
    }, 'json'
  );*/
}

function preencherInformacoesTerreno(idGround, tipo, lat = null, lng = null) {
  $.ajax({
    url: $.trim($("#terrenos_preencher_informacoes").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    method: "POST",
    data: {
      idGround: idGround,
      tipo: tipo,
      lat: lat,
      lng: lng,
    },
    beforeSend: function () { },
    success: function (data) {
      if (data) {

        $("#TER_Zoneamento").val(data.Zoneamento);
        $("#TER_CAMAX").val(data.CA);
        $("#TER_Setor").val(data.Setor);
        $("#TER_Quadra").val(data.Quadra);
        $("#TER_GabaritoAlturaPMSP").val(data.Gabarito);
        $("#TER_OperacaoUrbana").val(data.ZoneamentoEspecial.operacoes_urbanas);
        $.notify("Atualizado informações: Zoneamento, Ca Max , Operação Urbana e Gabarito Altura", "success");
      }
    },
    fail: function (data) {
      alert("falha!");
    },
  });
}

function atualizaStatusTerreno(ter_id, status_id) {
  $.ajax({
    url: $.trim($("#terrenos_atualizar_status").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    method: "POST",
    data: {
      TER_ID: ter_id,
      CAX_Status_ID: status_id
    },
    beforeSend: function () { },
    success: function (data) {
      if (!data.error) {
        $.notify("Status do terreno atualizado com sucesso!", "success");
      }
    },
    fail: function (data) {
      alert("falha!");
    },
  });
}

function editarSiglaConcorrenciaEstudo(
  chdid,
  empreendimentoSigla,
  empreendimentoDescricao
) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddTerrenosConcorrenciasEditar").val()),
    {
      EST_ID: $.trim($("#EST_ID").val()),
      CHDID_ID: $.trim(chdid),
      ETP_Sigla: $.trim(empreendimentoSigla),
      EMP_Descricao: $.trim(empreendimentoDescricao),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function atualizarSiglaConcorrenciaEstudo() {
  if ($.trim($("#ETP_Sigla").val()) == "") {
    $.notify("Sigla precisa ser informada.", "error");
    return;
  } else {
    preLoadingOpen();
    $("#btnSalvarSigla").prop("disabled", true);

    $.post(
      $.trim($("#hddTerrenosConcorrenciasSalvar").val()),
      {
        EST_ID: $.trim($("#EST_ID").val()),
        EMP_CodigoEmpreendimento: $.trim($("#EMP_CodigoEmpreendimento").val()),
        ETP_Sigla: $.trim($("#ETP_Sigla").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          consultarConcorrentes($.trim($("#EST_ID").val()));
          $.notify(data.mensagem, "success");
        } else {
          $.notify(data.mensagem, "error");
        }

        $(".modal").modal("hide");
        $("#btnSalvarSigla").prop("disabled", false);
        preLoadingClose();
        return;
      },
      "json"
    );
  }
}

function confirmarConferenciaEmpreendimento(
  empreendimentoID,
  empreendimentoDescricao,
  empreendimentoEndereco,
  empreendimentoBairro,
  empreendimentoCidade,
  empreendimentoTorres,
  empreendimentoNumeroTorres,
  empreendimentoRegiao,
  empreendimentoDataLancamento,
  empreendimentoDataEntrega,
  empreendimentoTipologia,
  empreendimentoAndamento,
  empreendimentoAreaTerreno,
  empreendimentoAreaConstruida,
  empreedimentoCadastradoPor,
  empreendimentoConferido
) {
  strHtml = "<b>Descrição:</b> " + empreendimentoDescricao + "<br>";

  if ($.trim(empreendimentoEndereco) != "")
    strHtml += "<b>Endereço:</b> " + empreendimentoEndereco + "<br>";
  if ($.trim(empreendimentoBairro) != "")
    strHtml += "<b>Bairro:</b> " + empreendimentoBairro + "<br>";
  if ($.trim(empreendimentoCidade) != "")
    strHtml += "<b>Cidade:</b> " + empreendimentoCidade + "<br>";
  if ($.trim(empreendimentoTorres) != "")
    strHtml += "<b>Torre:</b> " + empreendimentoTorres + "<br>";
  if ($.trim(empreendimentoNumeroTorres) != "")
    strHtml += "<b>Número Torres:</b> " + empreendimentoNumeroTorres + "<br>";
  if ($.trim(empreendimentoRegiao) != "")
    strHtml += "<b>Região:</b> " + empreendimentoRegiao + "<br>";
  if ($.trim(empreendimentoDataLancamento) != "")
    strHtml +=
      "<b>Data Lançamento:</b> " + empreendimentoDataLancamento + "<br>";
  if ($.trim(empreendimentoDataEntrega) != "")
    strHtml += "<b>Data Entrega:</b> " + empreendimentoDataEntrega + "<br>";
  if ($.trim(empreendimentoTipologia) != "")
    strHtml += "<b>Tipologia:</b> " + empreendimentoTipologia + "<br>";
  if ($.trim(empreendimentoAndamento) != "")
    strHtml += "<b>Andamento:</b> " + empreendimentoAndamento + "<br>";
  if ($.trim(empreendimentoAreaTerreno) != "")
    strHtml += "<b>Área Terreno:</b> " + empreendimentoAreaTerreno + "<br>";
  if ($.trim(empreendimentoAreaConstruida) != "")
    strHtml += "<b>Área Terreno:</b> " + empreendimentoAreaConstruida + "<br>";
  if ($.trim(empreedimentoCadastradoPor) != "")
    strHtml += "<b>Cadastrado por:</b> " + empreedimentoCadastradoPor;

  var strTitulo = $.trim($("#hddConfirmarConferencia").val());
  var strTipo = BootstrapDialog.TYPE_SUCCESS;
  var strCSS = "btn-success";
  if ($.trim(empreendimentoConferido) != "") {
    strTitulo = $.trim($("#hddConfirmarDesconferencia").val());
    strTipo = BootstrapDialog.TYPE_DANGER;
    strCSS = "btn-danger";
  }

  BootstrapDialog.show({
    title: strTitulo,
    size: BootstrapDialog.SIZE_WIDE,
    message: strHtml,
    type: strTipo,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: strCSS,
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          preLoadingOpen();

          $.post(
            $.trim($("#hddHiperdadosEmpreendimentoConferir").val()),
            {
              CHD_ID: $.trim(empreendimentoID),
            },
            function (data2) {
              //alert(data2); return;
              if (data2.sucesso == "true") {
                if (data2.conferir == strLabelSim) {
                  //$('#clique_'+data2.empreendimentoID).attr("onclick","confirmarConferenciaEmpreendimento('"+empreendimentoID+"', '"+empreendimentoDescricao+"', '"+empreendimentoEndereco+"', '"+empreendimentoBairro+"', '"+empreendimentoCidade+"', '"+empreendimentoTorres+"', '"+empreendimentoNumeroTorres+"', '"+empreendimentoRegiao+"', '"+empreendimentoDataLancamento+"', '"+empreendimentoDataEntrega+"', '"+empreendimentoTipologia+"', '"+empreendimentoAndamento+"', '"+empreendimentoAreaTerreno+"', '"+empreendimentoAreaConstruida+"', '"+empreedimentoCadastradoPor+"', '')");
                  $("#clique_" + data2.empreendimentoID).html(
                    $("#hddLabelDesconferir").val()
                  );
                } else {
                  //$('#clique_'+data2.empreendimentoID).attr("onclick","confirmarConferenciaEmpreendimento('"+empreendimentoID+"', '"+empreendimentoDescricao+"', '"+empreendimentoEndereco+"', '"+empreendimentoBairro+"', '"+empreendimentoCidade+"', '"+empreendimentoTorres+"', '"+empreendimentoNumeroTorres+"', '"+empreendimentoRegiao+"', '"+empreendimentoDataLancamento+"', '"+empreendimentoDataEntrega+"', '"+empreendimentoTipologia+"', '"+empreendimentoAndamento+"', '"+empreendimentoAreaTerreno+"', '"+empreendimentoAreaConstruida+"', '"+empreedimentoCadastradoPor+"', '"+empreendimentoConferido+"')");
                  $("#clique_" + data2.empreendimentoID).html(
                    $("#hddLabelConferir").val()
                  );
                }

                empreendimentoConferido = data2.conferido;

                $("#clique_" + data2.empreendimentoID).attr(
                  "onclick",
                  "confirmarConferenciaEmpreendimento('" +
                  empreendimentoID +
                  "', '" +
                  empreendimentoDescricao +
                  "', '" +
                  empreendimentoEndereco +
                  "', '" +
                  empreendimentoBairro +
                  "', '" +
                  empreendimentoCidade +
                  "', '" +
                  empreendimentoTorres +
                  "', '" +
                  empreendimentoNumeroTorres +
                  "', '" +
                  empreendimentoRegiao +
                  "', '" +
                  empreendimentoDataLancamento +
                  "', '" +
                  empreendimentoDataEntrega +
                  "', '" +
                  empreendimentoTipologia +
                  "', '" +
                  empreendimentoAndamento +
                  "', '" +
                  empreendimentoAreaTerreno +
                  "', '" +
                  empreendimentoAreaConstruida +
                  "', '" +
                  empreedimentoCadastradoPor +
                  "', '" +
                  empreendimentoConferido +
                  "')"
                );
                $("#conferir_" + data2.empreendimentoID).html(data2.conferir);
                $.notify(data2.mensagem, "success");
              } else {
                $.notify(data2.mensagem, "error");
              }
              $(".modal").modal("hide");
              preLoadingClose();
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function consultarHiperdadosEmpreendimentosPlantas() {
  $(".radioSearch").multiselect(getOptions(true));
  $("#dados-plantas").html(strCarregando);

  $.post(
    $.trim($("#hddHiperdadosEmpreendimentoPlantasConsultar").val()),
    {
      CHD_ID: $.trim($("#CHD_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#dados-plantas").html(data.strHtml);
      } else {
        $("#dados-plantas").html("");
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function confirmarEmpreendimentoPlantaConferir(
  empreendimentoID,
  plantaID,
  strMensagem
) {
  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem,
    size: BootstrapDialog.SIZE_WIDE,
    type: BootstrapDialog.TYPE_SUCCESS,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: "btn-success",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          preLoadingOpen();

          $.post(
            $.trim($("#hddHiperdadosEmpreendimentoPlantasConferir").val()),
            {
              CHD_ID: $.trim(empreendimentoID),
              EPL_ID: $.trim(plantaID),
            },
            function (data2) {
              //alert(data2); return;
              if (data2.sucesso == "true") {
                $.notify(data2.mensagem, "success");
              } else {
                $.notify(data2.mensagem, "error");
              }
              consultarHiperdadosEmpreendimentosPlantas();
              $(".modal").modal("hide");
              preLoadingClose();
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function confirmarEmpreendimentoPlantaDesconferir(
  empreendimentoID,
  plantaID,
  strMensagem
) {
  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem,
    size: BootstrapDialog.SIZE_WIDE,
    type: BootstrapDialog.TYPE_DANGER,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: "btn-danger",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          preLoadingOpen();

          $.post(
            $.trim($("#hddHiperdadosEmpreendimentoPlantasConferir").val()),
            {
              CHD_ID: $.trim(empreendimentoID),
              EPL_ID: $.trim(plantaID),
            },
            function (data2) {
              //alert(data2); return;
              if (data2.sucesso == "true") {
                $.notify(data2.mensagem, "success");
              } else {
                $.notify(data2.mensagem, "error");
              }
              consultarHiperdadosEmpreendimentosPlantas();
              $(".modal").modal("hide");
              preLoadingClose();
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function consultarHiperdadosEmpreendimentosTabelas() {
  $(".btn-formulario").prop("disabled", true);

  $(".radioSearch").multiselect(getOptions(true));
  $("#dados-tabelas").html(strCarregando);

  $.ajax({
    url: $.trim($("#hiperdados_empreendimentos_tabelas").val()),
    dataType: "json",
    cache: false,
    data: {
      CHD_ID: $.trim($("#CHD_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        $("#dados-tabelas").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#dados-tabelas").html(data.strHtml);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#dados-tabelas").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarHiperdadosEmpreendimentosAnexos() {
  $("#dados-anexos").html(strCarregando);

  $.ajax({
    url:
      $.trim($("#hddHiperdadosEmpreendimentosConsultar").val()) +
      "/" +
      $.trim($("#DOC_ID").val()),
    dataType: "json",
    cache: false,
    data: {
      CHD_ID: $.trim($("#CHD_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        $("#dados-anexos").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#dados-anexos").html(data.strHtml);
    })
    .fail(function (data) {
      $("#dados-anexos").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarHiperdadosEmpreendimentosAnexos() {
  var arrDescricoes = new Array();
  var arrTiposAnexos = new Array();
  var arrAnexos = new Array();
  var arrDados = new FormData();

  $("input[name='EAX_Descricao[]']").each(function () {
    if ($.trim($(this).val()) != "") {
      arrDescricoes.push($.trim($(this).val()));
    }
  });

  $("select[name='SGP_TiposAnexos[]'] option:selected").each(function () {
    if ($.trim($(this).val()) != "") {
      arrTiposAnexos.push($.trim($(this).val()));
    }
  });

  $("input[type='file'][name='EAX_Anexo[]']").each(function () {
    if ($(this).prop("files")[0] != undefined) {
      arrAnexos.push($(this).prop("files")[0]);
    }
  });

  if (arrDescricoes.length == 0 || arrDescricoes.length == null) {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else if (arrTiposAnexos.length == 0 || arrTiposAnexos.length == null) {
    $.notify("Tipo de anexo precisa ser informado.", "warn");
    return;
  } else if (arrAnexos.length == 0 || arrAnexos.length == null) {
    $.notify("Tipo de anexo precisa ser informado.", "warn");
    return;
  } else if (
    arrDescricoes.length == arrTiposAnexos.length &&
    arrAnexos.length == arrDescricoes.length &&
    arrTiposAnexos.length == arrAnexos.length
  ) {
    $("#btnSalvarHiperdadosEmpreendimentosAnexos").prop("disabled", true);
    var strLabel = $("#btnSalvarHiperdadosEmpreendimentosAnexos").html();
    $("#btnSalvarHiperdadosEmpreendimentosAnexos").html(strCarregando);
    preLoadingOpen();

    arrDados.append("CHD_ID", $("#CHD_ID").val());

    for (var i = 0; i < arrTiposAnexos.length; i++) {
      arrDados.append("CAX_ID[]", arrTiposAnexos[i]);
    }

    for (var i = 0; i < arrAnexos.length; i++) {
      arrDados.append("EAX_Anexo[]", arrAnexos[i]);
    }

    for (var i = 0; i < arrDescricoes.length; i++) {
      arrDados.append("EAX_Descricao[]", arrDescricoes[i]);
    }

    $.ajax({
      url: $.trim($("#hddHiperdadosEmpreendimentosAnexos").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarHiperdadosEmpreendimentosAnexos").prop("disabled", false);
        $("#btnSalvarHiperdadosEmpreendimentosAnexos").html(strLabel);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");
        consultarHiperdadosEmpreendimentosAnexos();
        $("#spnDataHoraUltimaAtualizacao").html(data.strDataAtual);
        $("#SGP_QuantidadeAnexos").val("");
        $("#SGP_QuantidadeAnexos").trigger("change");
      })
      .fail(function (data) {
        $("#btnSalvarHiperdadosEmpreendimentosAnexos").prop("disabled", false);
        $("#btnSalvarHiperdadosEmpreendimentosAnexos").html(strLabel);
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  } else {
    $.notify("Todas as informações devem ser preenchidas.", "warn");
  }
}

function calcularContasPagarValorIncidencia(
  valorContasPagar,
  percentualIncidencia
) {
  $("#CPI_ValorIncidencia").val("");

  $.post(
    $.trim($("#hddFinanceiroContasPagarCalcularIncidencia").val()),
    {
      CPG_Valor: $.trim(valorContasPagar),
      CPI_PercentualIncidencia: $.trim(percentualIncidencia),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#CPI_ValorIncidencia").val(data.douRetorno);
      } else {
        $.notify(data.mensagem, "error");
      }
      return;
    },
    "json"
  );
}

function calcularContasPagarPercentualImposto(
  valorIncidencia,
  percentualImposto
) {
  $("#CPI_ValorImposto").val("");

  $.post(
    $.trim($("#hddFinanceiroContasPagarCalcularImpostos").val()),
    {
      CPI_ValorIncidencia: $.trim(valorIncidencia),
      CPI_PercentualImposto: $.trim(percentualImposto),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#CPI_ValorImposto").val(data.douRetorno);
        verificaContasPagarValoresCalculados();
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function verificaContasPagarValoresCalculados() {
  $("#btnSalvarImpostos").prop("disabled", true);
  if (
    parseFloat($("#CPI_ValorIncidencia").val()) > 0 &&
    parseFloat($("#CPI_ValorImposto").val()) > 0
  ) {
    $("#btnSalvarImpostos").prop("disabled", false);
    $("#CPP_Imposto_0").val($("#CPI_ValorImposto").val());
  }
}

function capturarDocumentoTerrenoCorretor(documentoID, terrenoID, strMensagem) {
  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem,
    size: BootstrapDialog.SIZE_WIDE,
    type: BootstrapDialog.TYPE_PRIMARY,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: "btn-primary",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          preLoadingOpen();

          $.post(
            $.trim($("#hddCapturarDocumentoCorretor").val()),
            {
              DOC_ID: $.trim(documentoID),
              TER_ID: $.trim(terrenoID),
            },
            function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                consultarTerrenosDocumentos();
                $.notify(data.mensagem, "success");
              } else {
                $.notify(data.mensagem, "error");
              }
              $(".modal").modal("hide");
              preLoadingClose();
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function confirmarPropostaDocumentoExcluir(arquivoID, strLinha, strMensagem) {
  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem,
    size: BootstrapDialog.SIZE_WIDE,
    type: BootstrapDialog.TYPE_DANGER,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: "btn-danger",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          preLoadingOpen();

          $.post(
            $.trim($("#hddPropostasExcluirDocumento").val()),
            {
              PDC_ID: $.trim(arquivoID),
            },
            function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                $("#" + strLinha).html("");
                $.notify(data.mensagem, "success");
              } else {
                $.notify(data.mensagem, "error");
              }
              $(".modal").modal("hide");
              preLoadingClose();
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function executarRelacionamentoClientePosicaoCadastroAuxiliar() {
  $("#btnAdicionarCadastrosAuxiliares").css({
    position: "absolute",
    top: "0px",
    left: "82px",
  });

  $("#SOL_Status").selectpicker("refresh");

  $(".modal").on("hidden.bs.modal", function () {
    redir("", "parent");
  });
}

function salvarSolicitacoesClientes() {
  if ($.trim($("#SHI_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CAX_Solicitacao_ID").val()) == "") {
    $.notify("Categoria precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#SOL_Status").val()) == "") {
    $.notify("Status precisa ser informada.", "warn");
    return;
  } else {
    $("#btnSalvarSolicitacao").prop("disabled", true);
    var strLabel = $("#btnSalvarSolicitacao").html();
    $("#btnSalvarSolicitacao").html(strCarregando);

    $.ajax({
      url: $.trim($("#entidades_solicitacoes_salvar").val()),
      dataType: "json",
      cache: false,
      type: "POST",
      data: {
        SOL_ID: $.trim($("#SOL_ID").val()),
        SHI_ID: $.trim($("#SHI_ID").val()),
        CAX_ID: $.trim($("#CAX_Solicitacao_ID").val()),
        SHI_Descricao: $.trim($("#SHI_Descricao").val()),
        SOL_Status: $.trim($("#SOL_Status").val()),
      },
    })
      .success(function (data) {
        $("#btnSalvarSolicitacao").html(strLabel);
        $("#btnSalvarSolicitacao").prop("disabled", false);

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        $("#SHI_Descricao, #CAX_Solicitacao_ID, #SOL_Status").val("");
        $("#CAX_Solicitacao_ID, #SOL_Status").selectpicker("refresh");

        consultarSolicitacoesClientes($.trim($("#SOL_ID").val()));
      })
      .fail(function (data) {
        $("#btnSalvarSolicitacao").html(strLabel);
        $("#btnSalvarSolicitacao").prop("disabled", false);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarSolicitacoesClientes(solicitacaoID) {
  $("#solicitacoes-clientes").html(strCarregando);

  $.ajax({
    url: $.trim($("#entidades_solicitacoes_consultar").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      SOL_ID: $.trim(solicitacaoID),
    },
  })
    .success(function (data) {
      if (data.error) {
        $("#solicitacoes-clientes").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#solicitacoes-clientes").html(data.strHtml);
    })
    .fail(function (data) {
      $("#solicitacoes-clientes").html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function novaEntidade(tipoEntidade, descricaoEntidade, strAcao) {
  preLoadingOpen();
  $("#filtrar-padrao").modal("hide");
  $('#novaEntidadeID').remove();  

  $.ajax({
    url: $.trim($("#hddClientesNovoRapido").val()),
    dataType: "json",
    cache: false,
    data: {
      TPE_ID: $.trim(tipoEntidade),
      TPE_Descricao: $.trim(descricaoEntidade),
      strAcao: $.trim(strAcao),
    },
    type: "POST",
  }).success(function (data) {
      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }
        
      dialogAlert2(data.strTitulo, data.strHtml, 3, "novaEntidadeID");

      setTimeout(function (){
        $('#EMP_TipoPessoa, #UF_ID, #EMP_EstadoCivil, #ENC_Sexo, #ENT_RG_OrgaoEmissor_UF, #UF_OrgaoEmissor, #UF_IDConjuge, #UF_ID2').chosen();
        $("#hddCarregar").val("");
        $("#ENT_CPFCNPJ").unmask();
        $("#grp-cpfcnpj, #grp-razao, #grp-fantasia, .dadosPessoaFisica, .dadosPessoaJuridica, #dadosConjuge").hide();

        $("#EMP_TipoPessoa").change(function (){
          // limparModal();
          $("#ENT_CPFCNPJ").unmask();
          $("#grp-cpfcnpj, #grp-razao, #grp-fantasia, .dadosPessoaFisica, .dadosPessoaJuridica, #dadosConjuge").hide();
          $("#ENT_CPFCNPJ").on('blur');

          if ($.trim(this.value) == $.trim($("#hddFlagPessoaFisica").val())){
            $("#lblCPFCNPJ").html('CPF <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
            $("#ENT_CPFCNPJ").mask("999.999.999-99");
            $("#ENT_CPFCNPJ").attr("placeholder", "Informe o CPF");
            $("#lbl-razao").html('Nome <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
            $("#lbl-fantasia").html('Apelido <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
            $("#ENT_RazaoSocial").attr("placeholder", "Informe o nome");
            $("#grp-cpfcnpj, #grp-razao, .dadosPessoaFisica").show();

          }else if ($.trim(this.value) == $.trim($("#hddFlagPessoaJuridica").val())){
            $("#lblCPFCNPJ").html('CNPJ <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
            $("#ENT_CPFCNPJ").mask("99.999.999/9999-99");
            $("#ENT_CPFCNPJ").attr("placeholder", "Informe o CNPJ");
            $("#lbl-razao").html('Razão Social <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
            $("#lbl-fantasia").html('Nome Fantasia <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
            $("#ENT_RazaoSocial").attr("placeholder","Informe a razão social");
            $("#grp-razao, #grp-cpfcnpj, .dadosPessoaJuridica").show();
          }else if (this.value == $("#hddFlagPessoaPassaporte").val()){									

            console.log('PPPPPPP');
            $("#divCampos").show();
            $("#lblCPFCNPJ").html('Número Passaporte <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
            $("#ENT_CPFCNPJ").attr("placeholder", "Informe o número do passaporte");
            $("#ENT_CPFCNPJ").attr('maxlength', '15');
            $("#lbl-razao").html('Nome <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
            $("#lbl-fantasia").html('Apelido <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
            $("#ENT_RazaoSocial").attr("placeholder", "Informe a nome");
            $("#ENT_NomeFantasia").attr("placeholder", "Informe o apelido");
            $("#dadosPessoaFisica, #dadosPessoaJuridica").hide();
            $("#grp-cpfcnpj").show();
            $("#grp-razao").show();
            $("#grp-fantasia").show();
            $("#ENT_CPFCNPJ").off('blur');
          }
        });

        $("#EMP_EstadoCivil").change(function () {
          $("#dadosConjuge").hide();

          if ($.trim(this.value) != "" && $.trim($("#EMP_TipoPessoa").val()) != ""){
            $.ajax({
              url: $.trim($("#entidades_exibir_conjuge_vendas").val()),
              dataType: "json",
              cache: false,
              data: {
                EMP_TipoPessoa: $.trim($("#EMP_TipoPessoa").val()),
                TPE_ID: $.trim($("#TPE_ID").val()),
                EMP_EstadoCivil: $.trim(this.value),
              },
              type: "POST",
            }).success(function (data){
              //alert(data); return;
              if (data.sucesso == "true") {
                $("#dadosConjuge").show();
              }

            }).fail(function (data) {
              dialogAlert(strAtencao, data.responseText, 6);
            });
          }
        });
        preLoadingClose();
      }, 1000);

    }).fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarNovaEntidade() {
  //strAcao = C => Cotação | P => Pedido de Compra | T => Contrato | D => Agente | R => Contas à Pagar | I => Contas à Pagar Impostos
  if ($.trim($("#EMP_TipoPessoa").val()) == "") {
    $.notify("Tipo de pessoa precisa ser informada.", "error");
    return;
  } else if ($.trim($("#ENT_CPFCNPJ").val()) == "") {
    $.notify("CPF/CNPJ precisa ser informado.", "error");
    return;
  } else if ($.trim($("#ENT_RazaoSocial").val()) == "") {
    $.notify("Nome precisa ser informado.", "error");
    return;
  } else if ($.trim($("#ENT_Email").val()) == "") {
    $.notify("E-mail precisa ser informado.", "error");
    return;
  } else if (
    $.trim($("#ENT_Telefone").val()) == "" &&
    $.trim($("#ENT_Celular").val()) == ""
  ) {
    $.notify("Telefone ou celular precisa ser informado.", "error");
    return;
  } else {
    preLoadingOpen();
    $("#btnSalvarNovaEntidade").prop("disabled", true);

    var strAcao = $.trim($("#strAcao").val());

    $.post(
      $.trim($("#hddClientesSalvarRapido").val()),
      {
        GRE_ID: $.trim($("#GRE_ID").val()),
        TPE_ID: $.trim($("#TPE_ID").val()),
        EMP_TipoPessoa: $.trim($("#EMP_TipoPessoa").val()),
        ENT_CPFCNPJ: $.trim($("#ENT_CPFCNPJ").val()),
        ENT_RazaoSocial: $.trim($("#ENT_RazaoSocial").val()),
        ENT_Email: $.trim($("#ENT_Email").val()),
        ENT_Telefone: $.trim($("#ENT_Telefone").val()),
        ENT_Celular: $.trim($("#ENT_Celular").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          if (strAcao == "C") {
            selecionarFornecedoresCotacoes(data.ENT_ID);
          } else if (strAcao == "P" || strAcao == "T" || strAcao == "R") {
            $("#ENT_ID").val(data.ENT_ID);
            $("#ENT_Codigo").val(data.ENT_Codigo);
            $("#ENT_Pesquisar").val(data.ENT_RazaoSocial);
          } else if (strAcao == $.trim($("#hddAgenteComprador").val())) {
            adicionarCarteiraContratosAgentes(data.ENT_ID);
          } else if (strAcao == $.trim($("#hddAgenteImpostos").val())) {
            $("#ENT_Chosen_ID").append(
              "<option selected value='" +
              data.ENT_ID2 +
              "'>" +
              data.ENT_RazaoSocial +
              "</option>"
            );
            $("#ENT_Chosen_ID").trigger("chosen:updated");
          } else {
            $("#CLI_ID").val(data.ENT_ID);
            $("#CLI_Codigo").val(data.ENT_Codigo);
            $("#CLI_Pesquisar").val(data.ENT_RazaoSocial);
          }

          $.notify(data.mensagem, "success");
        } else {
          $.notify(data.mensagem, "error");
        }

        $("#btnSalvarNovaEntidade").prop("disabled", false);
        $("#novaEntidadeID").modal("hide");
        preLoadingClose();
      },
      "json"
    );
  }
}

function consultarPropostasVendas() {
  var strLabel = consultarPadraoInicial();
  var arrGruposEmpresas = new Array();
  var arrUnidades = new Array();
  var arrCondicoes = new Array();
  var arrAprovacoes = new Array();

  $("select[name='GRE_ID[]'] option:selected").each(function () {
    arrGruposEmpresas.push($(this).val());
  });

  $("select[name='UNI_ID[]'] option:selected").each(function () {
    arrUnidades.push($(this).val());
  });

  $("select[name='CON_ID[]'] option:selected").each(function () {
    arrCondicoes.push($(this).val());
  });

  $("select[name='PRO_Aprovacoes[]'] option:selected").each(function () {
    arrAprovacoes.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#hddVendasConsultarPropostas").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      GRE_ID: arrGruposEmpresas,
      UNI_ID: arrUnidades,
      CON_ID: arrCondicoes,
      SGP_Aprovacoes: arrAprovacoes,
      SGP_Codigo: $.trim($("#PRO_Codigo2").val()),
      SGP_DataPropostaInicial: $.trim($("#txtDataInicial").val()),
      SGP_DataPropostaFinal: $.trim($("#txtDataFinal").val()),
      SGP_Pesquisar: $.trim($("#PRO_Pesquisar2").val()),
    },
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);

      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function esqueciSenhaVendas() {
  $.post(
    $.trim($("#hddVendasEsqueciSenha").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2("Portal de Vendas - Esqueci senha ", data.strHtml, 3);
      }
      return;
    },
    "json"
  );
}

function enterEsqueciSenhaVendas(e) {
  if (e.keyCode == 13) {
    enviarEsqueciSenhaVendas();
  }
}

function enviarEsqueciSenhaVendas() {
  if ($.trim($("#USU_Login2").val()) == "") {
    $.notify("E-mail precisa ser informado.", "warn");
    return;
  } else {
    $("#USU_Login2").prop("disabled", true);

    $.post(
      $.trim($("#hddVendasEsqueciSenhaEnvio").val()),
      { USU_Email: $.trim($("#USU_Login2").val()) },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");
        } else {
          $.notify(data.mensagem, "error");
        }

        $(".modal").modal("hide");
        $("#USU_Login2").prop("disabled", false);
        return;
      },
      "json"
    );
  }
}

function validarNovaSenhaVendas() {
  if ($.trim($("#USU_Senha").val()) == "") {
    $.notify("Senha precisa ser informada.", "warn");
  } else if ($.trim($("#USU_Senha2").val()) == "") {
    $.notify("Confirmação da senha precisa ser informada.", "warn");
  } else if ($("#USU_Senha").val() != $("#USU_Senha2").val()) {
    $.notify("Senhas precisam ser iguais.", "warn");
  } else {
    $("#btnEnviar").prop("disabled", true);

    $.post(
      $.trim($("#hddEsqueciAtualizarVendas").val()),
      {
        USU_Cript: $.trim($("#hddCript").val()),
        USU_Senha: $.trim($("#USU_Senha").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");

          setTimeout(function () {
            redir(data.redir, "parent");
          }, 2000);
        }
        return;
      },
      "json"
    );
  }
}

function enterValidarNovaSenhaVendas(e) {
  if (e.keyCode == 13) {
    validarNovaSenhaVendas();
  }
}

function filtrarAdicionarReajustes() {
  if ($.trim($("#IND_ID").val()) == "") {
    $.notify("Indexador precisa ser informado.", "warn");
  } else if ($.trim($("#MES_ID").val()) == "") {
    $.notify("Mês do reajuste precisa ser informado.", "warn");
  } else if ($.trim($("#ANO_ID").val()) == "") {
    $.notify("Ano do reajuste precisa ser informado.", "warn");
  } else {
    $("#btnFiltrarExibirCarteirasReajustes").prop("disabled", true);
    var strLabel = $("#btnFiltrarExibirCarteirasReajustes").html();
    $(
      "#consultar-dados, #spnTotalRegistros, #btnFiltrarExibirCarteirasReajustes"
    ).html(strCarregando);
    preLoadingOpen();

    var arrEstruturas = new Array();
    var arrEntidades = new Array();

    $("select[name='EST_ID[]'] option:selected").each(function () {
      arrEstruturas.push($(this).val());
    });

    $("select[name='ENT_ID[]'] option:selected").each(function () {
      arrEntidades.push($(this).val());
    });

    $.ajax({
      url:
        $.trim($("#hddCarteirasReajustesExibir").val()) +
        "/" +
        $.trim($("#DOC_ID").val()),
      dataType: "json",
      cache: false,
      data: {
        ENT_ID: arrEntidades,
        EST_ID: arrEstruturas,
        IND_ID: $.trim($("#IND_ID").val()),
        MES_ID: $.trim($("#MES_ID").val()),
        ANO_ID: $.trim($("#ANO_ID").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnFiltrarExibirCarteirasReajustes").prop("disabled", false);
        $("#btnFiltrarExibirCarteirasReajustes").html(strLabel);
        preLoadingClose();

        if (data.error) {
          $("#consultar-dados, #spnTotalRegistros").html("");
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $("#consultar-dados").html(data.strHtml);
        $("#spnTotalRegistros").html(data.totalRegistros);

        if (data.totalRegistros > 1000) {
          $("#spnTotalRegistros").html("1000+");
        } else if (data.totalRegistros == 0) {
          $("#marcarTodos").hide();
        }
      })
      .fail(function (data) {
        $("#btnFiltrarExibirCarteirasReajustes").prop("disabled", false);
        $("#btnFiltrarExibirCarteirasReajustes").html(strLabel);
        $("#consultar-dados, #spnTotalRegistros").html("");
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });

    /*$.post($.trim($('#hddCarteirasReajustesExibir').val()),
      {
        ENT_ID: arrEntidades,
        EST_ID:	arrEstruturas,
        IND_ID: $.trim($('#IND_ID').val()),
        MES_ID: $.trim($('#MES_ID').val()),
        ANO_ID: $.trim($('#ANO_ID').val())
      },
      function(data){
        //alert(data); return;
        if (data.sucesso == 'true'){
          $('#consultar-dados').html(data.strHtml);

          if (data.totalRegistros > 0){
            carregarDataTables(true, false, false, bQtdPage = 5000000000000000000000, false, false, '', gruposEmpresasLista = false);

            setTimeout(function(){
              $('#cntConsulta_length').hide();
            }, 1000);
          }
        }else{
          $('#consultar-dados').html('');
        }

        $('#spnTotalRegistros').html(data.totalRegistros);

        if (data.totalRegistros > 500){
          $('#spnTotalRegistros').html("500+");

        }else if (data.totalRegistros == 0){
          $('#marcarTodos').hide();
        }

        $('#btnFiltrarExibirCarteirasReajustes').prop('disabled', false);
        $('#btnFiltrarExibirCarteirasReajustes').html(strLabel);
        preLoadingClose();
      }, 'json'
    );*/
  }
}

function checarBtnReajustes() {
  $("#btnCarteirasReajustes").hide();
  $(".chkCarteirasReajustes").each(function () {
    if (this.checked) {
      $("#btnCarteirasReajustes").show();
      return;
    }
  });
}

function confirmarExcluirPropostaVendas(propostaID, strMensagem) {
  BootstrapDialog.show({
    title: strInformacao,
    message: strMensagem,
    size: BootstrapDialog.SIZE_WIDE,
    type: BootstrapDialog.TYPE_DANGER,
    buttons: [
      {
        label: "Sair",
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: "Excluir",
        cssClass: "btn-danger",
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        action: function () {
          $("button").prop("disabled", true);

          $.post(
            $.trim($("#hddPropostaVendasExcluir").val()),
            {
              PRO_ID: $.trim(propostaID),
            },
            function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                $.notify(data.mensagem, "success");
                consultarPropostasVendas();
              } else {
                $.notify(data.mensagem, "error");
              }

              $("button").prop("disabled", false);
              $(".modal").modal("hide");
              return;
            },
            "json"
          );
        },
      },
    ],
  });
}

function confirmarCarteiraReajustes(strMensagem) {
  var arrParcelas = new Array();

  $("input[type='checkbox'][name='parcelas[]']:checked").each(function () {
    arrParcelas.push($(this).val());
  });

  if (arrParcelas.length > 0) {
    BootstrapDialog.show({
      title: strInformacao,
      message: strMensagem,
      size: BootstrapDialog.SIZE_WIDE,
      type: BootstrapDialog.TYPE_SUCCESS,
      buttons: [
        {
          label: strLabelNao,
          cssClass: "btn-danger btn-formulario",
          action: function (dialogItself) {
            dialogItself.close();
          },
        },
        {
          label: strLabelSim,
          cssClass: "btn-success btn-formulario",
          id: "btnConfirmarDialog",
          data: {
            js: "btn-confirm",
            "user-id": "3",
          },
          action: function () {
            $(".btn-formulario").prop("disabled", true);
            var strLabel = $("#btnConfirmarDialog").html();
            $("#btnConfirmarDialog").html(strCarregando);

            $.ajax({
              url: $.trim($("#hddCarteirasReajustesSalvar").val()),
              dataType: "json",
              cache: false,
              data: {
                IND_ID: $.trim($("#IND_ID").val()),
                MES_ID: $.trim($("#MES_ID").val()),
                ANO_ID: $.trim($("#ANO_ID").val()),
                arrParcelas: arrParcelas,
              },
              type: "POST",
            })
              .success(function (data) {
                $(".btn-formulario").prop("disabled", false);
                $("#btnConfirmarDialog").html(strLabel);

                if (data.error) {
                  dialogAlert(strInformacao, data.error.msg, 6);
                  return;
                }

                $(".modal").modal("hide");
                $.notify(data.mensagem, "success");
                filtrarAdicionarReajustes();
              })
              .fail(function (data) {
                $(".btn-formulario").prop("disabled", false);
                $("#btnConfirmarDialog").html(strLabel);

                dialogAlert(strAtencao, data.responseText, 6);
              });
          },
        },
      ],
    });
  } else {
    $.notify("Selecione no minímo 1 (UMA) opção para reajustar.", "warn");
    return;
  }
}

function calcularHiperdadosValorUnidadeEmpreendimento(
  areaPrivada,
  valorUnidade,
  valorMetro
) {
  var bolCalcular = false;
  if ($.trim($("#EST_Tipo").val()) == "M") {
    if (parseFloat(areaPrivada) > 0 && parseFloat(valorMetro) > 0) {
      bolCalcular = true;
    }
  } else if ($.trim($("#EST_Tipo").val()) == "U") {
    if (parseFloat(areaPrivada) > 0 && parseFloat(valorUnidade) > 0) {
      bolCalcular = true;
    }
  }

  if (bolCalcular) {
    $.post(
      $.trim($("#hddHiperdadosEmpreendimentosUnidadesCalcular").val()),
      {
        EST_Tipo: $.trim($("#EST_Tipo").val()),
        ENU_AreaPrivada: $.trim(areaPrivada),
        ENU_ValorM2Unidade: $.trim(valorMetro),
        ENU_ValorUnidade: $.trim(valorUnidade),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          if ($.trim($("#EST_Tipo").val()) == "M") {
            $("#ENU_ValorUnidade").val(data.douValorCalculado);
          } else {
            $("#ENU_ValorM2Unidade").val(data.douValorCalculado);
          }
        }
        return;
      },
      "json"
    );
  }
}

function clearChosen() {
  $(".form-control").val("");
  $(".form-control").trigger("chosen:updated");
}

function limparFormulario() {
  clearChosen();

  $(".selectpicker").selectpicker("refresh");
  $(".form-control").val("");
  $(".multiplos option").prop("selected", false);
  $(".multiplos option").prop("selected", false);
  $(".multiplos").multiselect("refresh");
  $('input[type="checkbox"]').prop("checked", false);

  $("#grp-mail").removeClass("has-error");
  $("#grp-mail").removeClass("has-success");
  $("#grp-cpfcnpj").removeClass("has-error");
  $("#grp-cpfcnpj").removeClass("has-success");
  $("#grp-descricao").removeClass("has-success");
  $("#grp-descricao").removeClass("has-error");
}

function propostaSelecionarCorretorVendas() {
  if ($.trim($("#GRE_ID").val()) != "") {
    preLoadingOpen();

    $.post(
      $.trim($("#hddPropostasCorretoresConsultar").val()),
      {
        GRE_ID: $.trim($("#GRE_ID").val()),
        PRO_ID: $.trim($("#PRO_ID").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          dialogAlert(strInformacao, data.strHtml, 3);
        } else {
          $.notify(data.mensagem, "error");
        }
        preLoadingClose();
      },
      "json"
    );
  } else {
    $.notify("Grupo empresa precisa ser informado.", "error");
  }
}

function gerarRelatorioEmpreendimentos() {
  $("#frmFormulario").attr("target", "_blank");
  $("#frmFormulario").attr("action", $.trim($("#hddAcao").val()));
  $("#frmFormulario").submit();
}

function consultarHiperdadosEmpreendimentos() {
  $(".btn-filtro").prop("disabled", true);
  var strLabel = $("#btnFiltrar").html();
  $("#btnFiltrar, #consultar-dados").html(strCarregando);
  preLoadingOpen();

  $("#frmFormulario").attr("target", "_self");
  $("#frmFormulario").attr("action", "");

  var arrIncorporadoras = new Array();
  var arrConstrutoras = new Array();
  var arrVendedores = new Array();
  var arrConferidos = new Array();
  var arrEstados = new Array();
  var arrCidades = new Array();
  var arrBairros = new Array();
  var arrUsuarios = new Array();
  var arrAndamentos = new Array();
  var arrCompletos = new Array();
  var arrTipologias = new Array();

  $("select[name='INC_ID[]'] option:selected").each(function () {
    arrIncorporadoras.push($(this).val());
  });

  $("select[name='CON_ID[]'] option:selected").each(function () {
    arrConstrutoras.push($(this).val());
  });

  $("select[name='VEN_ID[]'] option:selected").each(function () {
    arrVendedores.push($(this).val());
  });

  $("select[name='SIM_NAO[]'] option:selected").each(function () {
    arrConferidos.push($(this).val());
  });

  $("select[name='UF_ID[]'] option:selected").each(function () {
    arrEstados.push($(this).val());
  });

  $("select[name='CID_ID[]'] option:selected").each(function () {
    arrCidades.push($(this).val());
  });

  $("select[name='BAI_ID[]'] option:selected").each(function () {
    arrBairros.push($(this).val());
  });

  $("select[name='USU_ID[]'] option:selected").each(function () {
    arrUsuarios.push($(this).val());
  });

  $("select[name='CHD_Andamento[]'] option:selected").each(function () {
    arrAndamentos.push($(this).val());
  });

  $("select[name='SIM_NAO2[]'] option:selected").each(function () {
    arrCompletos.push($(this).val());
  });

  $("select[name='CHD_Tipologia[]'] option:selected").each(function () {
    arrTipologias.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#hddHiperdadosEmpreendimentoConsultar").val()),
    dataType: "json",
    cache: false,
    data: {
      INC_ID: arrIncorporadoras,
      CON_ID: arrConstrutoras,
      VEN_ID: arrVendedores,
      USU_Conferido_ID: arrConferidos,
      UF_ID: arrEstados,
      CID_ID: arrCidades,
      BAI_ID: arrBairros,
      USU_Responsavel_ID: arrUsuarios,
      CHD_Andamento: arrAndamentos,
      arrCompletos: arrCompletos,
      CHD_Tipologia: arrTipologias,
      EMP_Pesquisar: $.trim($("#EMP_Pesquisar2").val()),
      CHD_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      CHD_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
      CHD_DataLancamentoInicial: $.trim($("#CHD_DataLancamentoInicial").val()),
      CHD_DataLancamentoFinal: $.trim($("#CHD_DataLancamentoFinal").val()),
      CHD_DataEntregaInicial: $.trim($("#CHD_DataEntregaInicial").val()),
      CHD_DataEntregaFinal: $.trim($("#CHD_DataEntregaFinal").val()),
      CHD_DataHoraUltimaAtualizacaoInicial: $.trim(
        $("#CHD_DataHoraUltimaAtualizacaoInicial").val()
      ),
      CHD_DataHoraUltimaAtualizacaoFinal: $.trim(
        $("#CHD_DataHoraUltimaAtualizacaoFinal").val()
      ),
      SGP_Paginacao: $.trim($("#SGP_Paginacao").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#btnFiltrar").html(strLabel);
      preLoadingClose();

      if (data.error) {
        //$('#consultar-dados').html('');
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#consultar-dados").html(data.strHtml);
      $("#pagination").html(data.pagination);
      $("#pagination").on("click", "a", function (e) {
        e.preventDefault();
        var pageno = $(this).attr("data-ci-pagination-page");
        loadPagination(data.url, pageno, data.arrFiltros);
      });
    })
    .fail(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#consultar-dados").html("");
      $("#btnFiltrar").html(strLabel);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarHiperdadosEmpreendimentos(e) {
  if (e.keyCode == 13) {
    consultarHiperdadosEmpreendimentos();
  }
}

function visualizarSolicitacoesClientes(solicitacaoID) {
  preLoadingOpen();

  $.post(
    $.trim($("#hddSolicitacoesDetalhes").val()),
    {
      SOL_ID: $.trim(solicitacaoID),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert(data.strTitulo, data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function exibirCadastroAuxiliares(
  strModulo = "",
  intTipoCadastroAuxiliares = "",
  strTipoCadastroAuxiliares = "",
  intIDRetorno = ""
) {
  var strHtml =
    "<form class='form-horizontal' id='frmFormularioDialog' method='post' onSubmit='return false;' enctype='multipart/form-data'>";

  if ($.trim(intTipoCadastroAuxiliares) != "") {
    strHtml +=
      "<input type='hidden' id='hddTipoCadastroAuxiliarSelecionado' value='" +
      $.trim(intTipoCadastroAuxiliares) +
      "'/>";
  } else {
    strHtml +=
      "<input type='hidden' id='hddTipoCadastroAuxiliarSelecionado' value='" +
      $.trim($("#TCX_ID").val()) +
      "'/>";
  }

  strHtml +=
    "<input type='hidden' id='hddIDRetorno' value='" +
    $.trim(intIDRetorno) +
    "'/>";
  strHtml +=
    "Descrição: <input type='text' onKeyPress='enterSalvarCadastrosAuxiliares(event);' name='CAX_Descricao' id='CAX_Descricao' autocomplete='off' value='' style='width:300px;' placeholder='Informe a descrição' class='form-control'/>";
  strHtml +=
    "<button type='button' style='position:absolute;top:37px;right:507px;' id='btnSalvarCadastroAuxiliares' class='btn btn-sm btn-primary btn-formulario pull-right' onClick=\"salvarCadastrosAuxiliaresRapido();\">";
  strHtml +=
    "<i class='glyphicon glyphicon-ok-circle'></i> " +
    $.trim($("#hddLabelBtnCadastrar").val());
  strHtml += "</button>";
  strHtml += "</form>";

  if ($.trim(strModulo) != "") {
    dialogAlert2(
      $.trim(strTipoCadastroAuxiliares) + " (" + $.trim(strModulo) + ")",
      strHtml,
      3,
      "modalDialogCadastrosAuxiliaresRapido"
    );
  } else {
    dialogAlert2(
      $.trim($("#TCX_Descricao").val()) +
      " (" +
      $.trim($("#MOD_Descricao").val()) +
      ")",
      strHtml,
      3,
      "modalDialogCadastrosAuxiliaresRapido"
    );
  }

  setTimeout(function () {
    $("#CAX_Descricao").focus();
  }, 1000);
}

function salvarCadastrosAuxiliaresRapido() {
  if ($.trim($("#CAX_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnSalvarCadastroAuxiliares").html();
    $("#btnSalvarCadastroAuxiliares").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddSalvarCadastrosAuxiliaresRapido").val()),
      dataType: "json",
      cache: false,
      data: {
        TCX_ID: $.trim($("#hddTipoCadastroAuxiliarSelecionado").val()),
        GRE_ID: $.trim($("#GRE_ID").val()),
        CAX_Descricao: $.trim($("#CAX_Descricao").val()),
        SGP_InputRetorno: $.trim($("#hddIDRetorno").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        if (
          data.SGP_InputRetorno == undefined ||
          $.trim(data.SGP_InputRetorno) == "" ||
          data.SGP_InputRetorno == false
        ) {
          data.SGP_InputRetorno = "CAX_ID";

          if ($("#CAX_Dialog_ID").val() != undefined) {
            data.SGP_InputRetorno = "CAX_Dialog_ID";
          }
        }

        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvarCadastroAuxiliares").html(strLabel);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $("#modalDialogCadastrosAuxiliaresRapido").modal("hide");
        //diegoraphael

        var strAppend =
          "<option selected value='" +
          data.CAX_ID +
          "'>" +
          data.CAX_Descricao +
          "</option>";

        $('select[name="' + data.SGP_InputRetorno + '"]').append(strAppend);
        $('select[name="' + data.SGP_InputRetorno + '"]').trigger(
          "chosen:updated"
        );
        // $('select[name="'+data.SGP_InputRetorno+'"]').selectpicker('refresh');
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvarCadastroAuxiliares").html(strLabel);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function enterSalvarCadastrosAuxiliares(e) {
  if (e.keyCode == 13) {
    salvarCadastrosAuxiliaresRapido();
  }
}

function salvarInsumoRapido() {
  if ($.trim($("#UNM_ID4").val()) == "") {
    $.notify("Unidade de medida precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#INS_DescricaoRapido").val()) == "") {
    $.notify("Descrição do insumo precisa ser informada.", "warn");
    return;
  } else {
    $("#btnAdicionarInsumosRapido").prop("disabled", true);
    var strLabel = $("#btnAdicionarInsumosRapido").html();
    $("#btnAdicionarInsumosRapido").html(strCarregando);

    $.ajax({
      url: $.trim($("#insumos_salvar_rapido").val()),
      dataType: "json",
      cache: false,
      data: {
        UNM_ID: $.trim($("#UNM_ID4").val()),
        INS_Descricao: $.trim($("#INS_DescricaoRapido").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnAdicionarInsumosRapido").prop("disabled", false);
        $("#btnAdicionarInsumosRapido").html(strLabel);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $("#INS_ID").val(data.codigo);
        $("#INS_Codigo").val(data.numero);
        $("#INS_Pesquisar").val(data.descricao);
        $("#UNM_ID").val(data.medida);
        $("#dialogNovoRapidoInsumos").modal("hide");

        $.notify(data.mensagem, "success");
      })
      .fail(function (data) {
        $("#btnAdicionarInsumosRapido").prop("disabled", false);
        $("#btnAdicionarInsumosRapido").html(strLabel);
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarUnidadesProdutosHistoricos() {
  $("#dados-historicos").html(strCarregando);

  $.post(
    $.trim($("#hddProdutosUnidadesHistoricosConsultar").val()),
    {
      PRD_ID: $.trim($("#PRD_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#dados-historicos").html(data.strHtml);
      } else {
        $("#dados-historicos").html("");
        $.notify(data.mensagem, "error");
      }
      return;
    },
    "json"
  );
}

function editarViabilidadesVendas(viabilidadeID, estruturaID) {
  $.post(
    $.trim($("#hddViabilidadesVendasEditar").val()),
    {
      VIA_ID: $.trim(viabilidadeID),
      EST_ID: $.trim(estruturaID),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#btnAdicionarVendasViabilidades").html(
          "<i class='glyphicon glyphicon-ok'></i> Salvar"
        );

        $("#EST_ID").val(data.arrDados[0].EST_ID);
        $("#EST_TipoUnidade").val(data.arrDados[0].EST_TipoUnidade);
        $("#EST_QuantidadeUnidade").val(data.arrDados[0].EST_QuantidadeUnidade);
        $("#EST_QuantidadePermutas").val(
          data.arrDados[0].EST_QuantidadePermutas
        );
        $("#EST_AreaPrivada").val(data.arrDados[0].EST_AreaPrivada);
        $("#EST_Fase").val(data.arrDados[0].EST_Fase);
        $("#SGP_SIMNao").val(data.arrDados[0].EST_TravarMesEntrega);

        $("#EST_Tipo").val(data.arrDados[0].EST_Tipo);

        if (data.arrDados[0].EST_Tipo == "U") {
          $("#EST_PrecoM2").val(data.arrDados[0].EST_ValorUnidade);
        } else {
          $("#EST_PrecoM2").val(data.arrDados[0].EST_PrecoM2);
        }

        $("#ATV_ID").val(data.arrDados[0].ATV_ID);
        $("#EST_JurosTabela").val(data.arrDados[0].EST_JurosTabela);
        $("#EST_PeriodoInicioVenda").val(
          data.arrDados[0].EST_PeriodoInicioVenda
        );
        $("#EST_PeriodoJuros").val(data.arrDados[0].EST_PeriodoJuros);
        $("#EST_PeriodoEntrega").val(data.arrDados[0].EST_PeriodoEntrega);
        $("#EST_MinhaCasaMinhaVida").val(
          data.arrDados[0].EST_MinhaCasaMinhaVida
        );
        $("#ATV_ID").trigger("chosen:updated");
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function editarCarteiraParcelaBaixaPagamento(
  parcelaID,
  parcelaNumero,
  indexadorID,
  dataBase,
  indexadorIDPosEntrega,
  retroatividade,
  dataVencimento,
  FlagCorrecao
) {
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#hddCarteirasContratosParcelasEditar").val()),
    dataType: "json",
    cache: false,
    data: {
      CTP_ID: $.trim(parcelaID),
      CTP_Numero: $.trim(parcelaNumero),
      IND_ID: $.trim(indexadorID),
      CTP_DataBase: $.trim(dataBase),
      IND_ID_PosDataEntrega: $.trim(indexadorIDPosEntrega),
      CTP_RetroatividadeIndice: $.trim(retroatividade),
      CTP_DataVencimento: $.trim(dataVencimento),
      CTP_FlagCorrecao: $.trim(FlagCorrecao),
    },
    type: "POST",
  })
    .success(function (data) {
      preLoadingClose();

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      dialogAlert(data.strTitulo, data.strHtml, 3);
    })
    .fail(function (data) {
      preLoadingClose();

      dialogAlert(strAtencao, data.statusText + " (" + data.status + ")", 6);
    });
}

function atualizarCarteirasContratosParcelas() {
  if ($.trim($("#IND_ID").val()) == "") {
    $.notify("Indexador Pré precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CTP_DataBase").val()) == "") {
    $.notify("Data Base precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CTP_DataVencimento").val()) == "") {
    $.notify("Data Vencimento precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#IND_ID_PosDataEntrega").val()) == "") {
    $.notify("Indexador Pré precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CTP_RetroatividadeIndice").val()) == "") {
    $.notify("Defasagem precisa ser informada", "warn");
    return;
  } else if ($.trim($("#SEL_SimNao5").val()) == "") {
    $.notify("Correção precisa ser informada", "warn");
    return;
  } else {
    $("#btnAtualizarContratosParcelas").attr("disabled", true);
    var strLabel = $("#btnAtualizarContratosParcelas").html();
    $("#btnAtualizarContratosParcelas").html(strCarregando);

    $.ajax({
      url: $.trim($("#hddCarteirasContratosParcelasSalvar").val()),
      dataType: "json",
      cache: false,
      data: {
        CTP_ID: $.trim($("#CTP_ID").val()),
        IND_ID: $.trim($("#IND_ID").val()),
        CTP_DataBase: $.trim($("#CTP_DataBase").val()),
        CTP_DataVencimento: $.trim($("#CTP_DataVencimento").val()),
        IND_ID_PosDataEntrega: $.trim($("#IND_ID_PosDataEntrega").val()),
        CTP_RetroatividadeIndice: $.trim($("#CTP_RetroatividadeIndice").val()),
        CTP_FlagCorrecao: $.trim($("#SEL_SimNao5").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnAtualizarContratosParcelas").attr("disabled", false);
        $("#btnAtualizarContratosParcelas").html(strLabel);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $(".modal").modal("hide");
        $.notify(data.mensagem, "success");
        consultarCarteiraContratosParcelas();
      })
      .fail(function (data) {
        $("#btnAtualizarContratosParcelas").attr("disabled", false);
        $("#btnAtualizarContratosParcelas").html(strLabel);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function confirmarPadrao(strRota, strTitulo, strMensagem, strTipo, callBack) {
  var types = BootstrapDialog.TYPE_DEFAULT;
  var btnCSS = "btn-default";

  if (strTipo == strBootstrapCodigoInfo) {
    //2
    types = BootstrapDialog.TYPE_INFO;
    btnCSS = "btn-info";
  } else if (strTipo == strBootstrapCodigoPrimary) {
    //3
    types = BootstrapDialog.TYPE_PRIMARY;
    btnCSS = "btn-primary";
  } else if (strTipo == strBootstrapCodigoSuccess) {
    //4
    types = BootstrapDialog.TYPE_SUCCESS;
    btnCSS = "btn-success";
  } else if (strTipo == strBootstrapCodigoWarning) {
    //5
    types = BootstrapDialog.TYPE_WARNING;
    btnCSS = "btn-warning";
  } else if (strTipo == strBootstrapCodigoDanger) {
    //6
    types = BootstrapDialog.TYPE_DANGER;
    btnCSS = "btn-danger";
  }

  BootstrapDialog.show({
    title: strTitulo,
    message: strMensagem,
    size: BootstrapDialog.SIZE_WIDE,
    type: types,
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: btnCSS,
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        id: "btnConfirmPadraoYes",
        action: function () {
          $("#btnConfirmPadraoYes").prop("disabled", true);
          $("#btnConfirmPadraoYes").html(strCarregando);

          $.ajax({
            url: $.trim(strRota),
            dataType: "json",
            cache: false,
            data: {
              valor: "1",
            },
            type: "POST",
          })
            .success(function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                if (data.redir != undefined) {
                  redir(data.redir, "parent");
                } else if ($.trim(callBack)) {
                  eval(callBack);
                  $.notify(data.mensagem, "success");
                } else {
                  $.notify(data.mensagem, "success");
                }
              } else {
                $.notify(data.mensagem, "error");
              }

              $("#btnConfirmPadraoYes").prop("disabled", false);
              $("#btnConfirmPadraoYes").html(strLabelSim);
              $(".modal").modal("hide");
              return;
            })
            .fail(function (data) {
              //alert(data); return;
              dialogAlert(strAtencao, data.responseText, 6);
              preLoadingClose();
              return;
            });
        },
      },
    ],
  });
}

function confirmar(
  strRota,
  strTitulo,
  strMensagem,
  strTipo,
  inputNameCheckboxArray,
  htmlNovaRotaComplemento,
  arrNovosCampos,
  textoAdicional = "",
  novosCamposObrigatorios = true,
  strNovoValorParametro = null
) {
  var arrChecks = new Array();
  var arrCampos = new Array();
  var arrCamposNovos = new Array();
  var arrUploads = new Array();

  if (inputNameCheckboxArray != undefined) {
    $(
      "input[type=checkbox][name='" + inputNameCheckboxArray + "']:checked"
    ).each(function () {
      arrChecks.push($(this).val());
    });

    if (arrChecks.length == 0) {
      $.notify(
        "Selecione no minímo 1 (UMA) opção para executar a operação.",
        "warn"
      );
      return;
    }
  }

  if (arrNovosCampos != undefined) {
    var bolNovosCamposOk = true;
    for (var i = 0; i < arrNovosCampos.length; i++) {
      if (novosCamposObrigatorios) {
        if ($("#" + arrNovosCampos[i]).val() != undefined) {
          if (
            $.trim($("#" + arrNovosCampos[i]).val()) == "" ||
            $.trim($("#" + arrNovosCampos[i]).val()) == "0,00"
          ) {
            $.notify("Verifique se todos os campos estão preenchidos.", "warn");
            return;
          }
        } else {
          bolNovosCamposOk = false;
        }
      }

      arrCamposNovos[i] = $.trim($("#" + arrNovosCampos[i]).val());
    }

    if (!bolNovosCamposOk && $("#" + arrNovosCampos).val() != undefined) {
      if (
        $.trim($("#" + arrNovosCampos).val()) == "" ||
        $.trim($("#" + arrNovosCampos).val()) == "0,00"
      ) {
        $.notify("Verifique se todos os campos estão preenchidos.", "warn");
        return;
      }

      bolNovosCamposOk = true;
      arrCamposNovos = $.trim($("#" + arrNovosCampos).val());
    }

    if (!bolNovosCamposOk) {
      $.notify(
        "ATENÇÃO: Verifique a lista de valores dos campos se é ou são válido(s).",
        "error"
      );
      return;
    }
  }

  var types = BootstrapDialog.TYPE_DEFAULT;
  var btnCSS = "btn-default";

  if (strTipo == strBootstrapCodigoInfo) {
    //2
    types = BootstrapDialog.TYPE_INFO;
    btnCSS = "btn-info";
  } else if (strTipo == strBootstrapCodigoPrimary) {
    //3
    types = BootstrapDialog.TYPE_PRIMARY;
    btnCSS = "btn-primary";
  } else if (strTipo == strBootstrapCodigoSuccess) {
    //4
    types = BootstrapDialog.TYPE_SUCCESS;
    btnCSS = "btn-success";
  } else if (strTipo == strBootstrapCodigoWarning) {
    //5
    types = BootstrapDialog.TYPE_WARNING;
    btnCSS = "btn-warning";
  } else if (strTipo == strBootstrapCodigoDanger) {
    //6
    types = BootstrapDialog.TYPE_DANGER;
    btnCSS = "btn-danger";
  }

  if (htmlNovaRotaComplemento !== undefined) {
    if ($.trim(htmlNovaRotaComplemento) != "") {
      $.ajax({
        url: $.trim(htmlNovaRotaComplemento),
        dataType: "json",
        cache: false,
        data: {
          SGP_Validar: true,
          arrSelecionados: arrChecks,
          SGP_NovoValorParametro: $.trim(strNovoValorParametro),
        },
        type: "POST",
      })
        .success(function (data) {
          if (data.error) {
            dialogAlert(strAtencao, data.error.msg, 6);
            return;
          }

          strTitulo = data.strTitulo;
          strMensagem += data.strHtml;
          arrCampos = data.arrCampos; //.concat(data.arrDescricao)
          arrUploads = data.arrUploadFiles;

          if (data.executar !== undefined) {
            setTimeout(function () {
              eval(data.executar);
            }, 1500);
          }
        })
        .fail(function (data) {
          $("button").prop("disabled", false);
          $("#btnConfirmPadraoYes").html(strLabelSim);
          dialogAlert(strAtencao, data.responseText, 6);
          return;
        });
    }
  }

  if ($.trim(textoAdicional) != "") {
    strMensagem += "<br>" + $.trim(textoAdicional);
  }

  setTimeout(function () {
    BootstrapDialog.show({
      title: strTitulo,
      message: strMensagem,
      size: BootstrapDialog.SIZE_WIDE,
      type: types,
      id: "dialogConfirmBootstrap",
      buttons: [
        {
          label: strLabelNao,
          action: function (dialogItself) {
            dialogItself.close();
          },
        },
        {
          label: strLabelSim,
          cssClass: btnCSS,
          data: {
            js: "btn-confirm",
            "user-id": "3",
          },
          id: "btnConfirmPadraoYes",
          action: function () {
            $("button").prop("disabled", true);
            $("#btnConfirmPadraoYes").html(strCarregando);

            var strCampo1 = "";
            if ($("#CON_ID").val() != undefined) {
              strCampo1 = $.trim($("#CON_ID").val());
            }

            var arrResultados = new FormData();
            for (var intI = 0; intI < arrChecks.length; intI++) {
              arrResultados.append("arrChecks[]", arrChecks[intI]);
            }

            arrResultados.append("valor", "1");
            arrResultados.append("campo1", strCampo1);

            if (arrCampos != undefined) {
              for (var i = 0; i < arrCampos.length; i++) {
                if (arrCampos[i].includes("[]")) {
                  $('input[name^="' + arrCampos[i] + '"]').each(function () {
                    arrResultados.append(arrCampos[i], $.trim($(this).val()));
                  });
                } else {
                  arrResultados.append(
                    arrCampos[i],
                    $.trim($("#" + arrCampos[i]).val())
                  );
                }
              }
            }

            if (arrCamposNovos != undefined) {
              if ($.isArray(arrCamposNovos)) {
                for (var i = 0; i < arrCamposNovos.length; i++) {
                  arrResultados.append("arrCamposNovos[]", arrCamposNovos[i]);
                }
              } else {
                arrResultados.append("arrCamposNovos[]", arrCamposNovos);
              }
            }

            if (arrUploads != undefined) {
              if (arrUploads.length > 0) {
                for (var i = 0; i < arrUploads.length; i++) {
                  $("input[type='file'][name='" + arrUploads[i] + "[]']").each(
                    function () {
                      for (var a = 0; a < $(this).prop("files").length; a++) {
                        if ($.trim($(this).prop("files")[a]) != "") {
                          arrResultados.append(
                            arrUploads[i] + "[]",
                            $(this).prop("files")[a]
                          );
                        }
                      }
                    }
                  );
                }
              }
            }

            $.ajax({
              url: $.trim(strRota),
              dataType: "json",
              cache: false,
              contentType: false,
              processData: false,
              data: arrResultados,
              type: "POST",
            })
              .success(function (data) {
                $("button").prop("disabled", false);
                $("#btnConfirmPadraoYes").html(strLabelSim);

                if (data.limpar_html != undefined) {
                  $("#" + data.limpar_html).html("");
                }

                if (data.trigger_js != undefined) {
                  $(data.trigger_js).trigger("blur");
                }

                if (data.escondercss != undefined) {
                  $(".escondercss").hide();
                }

                if (data.esconder != undefined) {
                  $("#" + data.esconder).modal("hide");
                }

                if (data.modalclose != undefined) {
                  $(".modal").modal("hide");
                }

                if (data.error) {
                  dialogAlert(strAtencao, data.error.msg, 6);
                  return;
                }

                $("#dialogConfirmBootstrap").modal("hide");

                if (data.executar !== undefined) {
                  eval(data.executar);
                }

                if (data.filtrar !== undefined) {
                  $("#btnFiltrar").trigger("click");
                }

                if (data.SGP_Campo !== undefined) {
                  $("#" + data.SGP_Campo[0]).val(data.SGP_Campo[1]);
                }

                if (data.mensagem !== undefined) {
                  if (data.sucesso == "false") {
                    $.notify(data.mensagem, "error");
                  } else {
                    $.notify(data.mensagem, "success");
                  }
                }

                if (data.strHtml !== undefined) {
                  dialogAlert(data.strTitulo, data.strHtml, data.strTipo);

                  if (data.paginacao == true) {
                    setTimeout(function () {
                      requireDataTablesDialog(true, true);
                    }, 1000);
                  }
                }

                if (data.redir !== undefined) {
                  setTimeout(function () {
                    redir(data.redir);
                  }, 1500);
                }
              })
              .fail(function (data) {
                $("button").prop("disabled", false);
                $("#btnConfirmPadraoYes").html(strLabelSim);
                preLoadingClose();

                dialogAlert(strAtencao, data.responseText, 6);
              });
          },
        },
      ],
    });

    setInitFunctions();
  }, 1000);
}

function detalhes(
  strRota,
  strTitulo,
  strTipo,
  inputNameCheckboxArray,
  arrMultiplosName,
  multiplosObrigatorio = false,
  arrNovosCampos,
  arrValores
) {
  var arrChecks      = new Array();
  var arrCampos      = new Array();
  var arrMultiplos   = new Array();

  if (inputNameCheckboxArray != undefined) {
    $(
      "input[type=checkbox][name='" + inputNameCheckboxArray + "']:checked"
    ).each(function () {
      arrChecks.push($(this).val());
    });

    if (arrChecks.length == 0) {
      $.notify(
        "Selecione no minímo 1 (UMA) opção para executar a operação.",
        "warn"
      );
      return;
    }
  }

  if (arrMultiplosName != undefined) {
    $("select[name='" + arrMultiplosName + "'] option:selected").each(
      function () {
        arrMultiplos.push($(this).val());
      }
    );

    if (multiplosObrigatorio === true && arrMultiplos.length == 0) {
      $.notify(
        "Selecione no minímo 1 (UMA) opção para executar a operação.",
        "warn"
      );
      return;
    }
  }

  if (arrNovosCampos != undefined) {
    var bolNovosCamposOk = true;
    for (var i = 0; i < arrNovosCampos.length; i++) {
      if ($("#" + arrNovosCampos[i]).val() != undefined) {
        if (
          $.trim($("#" + arrNovosCampos[i]).val()) == "" ||
          $.trim($("#" + arrNovosCampos[i]).val()) == "0,00"
        ) {
          $.notify("Verifique se todos os campos estão preenchidos.", "warn");
          return;
        }
      } else {
        bolNovosCamposOk = false;
      }
      arrCampos[i] = $.trim($("#" + arrNovosCampos[i]).val());
    }

    if (!bolNovosCamposOk && $("#" + arrNovosCampos).val() != undefined) {
      if (
        $.trim($("#" + arrNovosCampos).val()) == "" ||
        $.trim($("#" + arrNovosCampos).val()) == "0,00"
      ) {
        $.notify("Verifique se todos os campos estão preenchidos.", "warn");
        return;
      }

      bolNovosCamposOk = true;
      arrCampos = $.trim($("#" + arrNovosCampos).val());
    }

    if (!bolNovosCamposOk) {
      $.notify(
        "ATENÇÃO: Verifique a lista de valores dos campos se é ou são válido(s).",
        "error"
      );
      return;
    }
  }

  var types = BootstrapDialog.TYPE_DEFAULT;
  if (strTipo == 2) {
    types = BootstrapDialog.TYPE_INFO;
  } else if (strTipo == 3) {
    types = BootstrapDialog.TYPE_PRIMARY;
  } else if (strTipo == 4) {
    types = BootstrapDialog.TYPE_SUCCESS;
  } else if (strTipo == 5) {
    types = BootstrapDialog.TYPE_WARNING;
  } else if (strTipo == 6) {
    types = BootstrapDialog.TYPE_DANGER;
  }

  $.ajax({
    url: $.trim(strRota),
    dataType: "json",
    cache: false,
    data: {
      arrSelecionados: arrChecks,
      arrMultiplos: arrMultiplos,
      strTipo: strTipo,
      arrCampos: arrCampos,
      SGP_Valores: arrValores
    },
    type: "POST",
  }).success(function (data) {
      $("#btnConfirmPadraoYes").prop("disabled", false);
      $("#btnConfirmPadraoYes").html(strLabelSim);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      if (data.strTitulo != undefined) {
        if ($.trim(data.strTitulo) != "") {
          strTitulo = data.strTitulo;
        }
      }

      BootstrapDialog.show({
        id: "modalBootstrapDialogDetail",
        size: BootstrapDialog.SIZE_WIDE,
        type: types,
        title: $.trim(strTitulo),
        message: data.strHtml,
      });

      if (data.paginacao != undefined) {
        if (data.paginacao == true) {
          setTimeout(function () {
            requireDataTablesDialog(true, true);
          }, 1000);
        }
      }

      setTimeout(function () {
        setInitFunctions();

        if (data.multiplos != undefined) {
          $(".multiplos").multiselect(getOptions());
        }

        if (data.chosen != undefined) {
          $(data.chosen).chosen();
        }

        if (data.executar != undefined) {
          eval(data.executar);
        }

        if (data.telacheia != undefined) {
          telaCheia();
        }

        if (data.slideShow != undefined) {
          $("#slideShow").craftyslide();
        }

        if (data.accordion != undefined) {
          $(function () {
            var active = true;
            $("#collapse-init").click(function () {
              if (active) {
                active = false;
                $(".panel-collapse").collapse("show");
                $(".panel-title").attr("data-toggle", "");
                $(this).text("Esconder itens");
              } else {
                active = true;
                $(".panel-collapse").collapse("hide");
                $(".panel-title").attr("data-toggle", "collapse");
                $(this).text("Exibir itens");
              }
            });

            $("#accordion").on("show.bs.collapse", function () {
              if (active) $("#accordion .in").collapse("hide");
            });
          });
        }

        $(".clicarLinkInsumos").on("click", function () {
          filtrarInsumosNovo(data.arrValores[$(this).attr("alt")]);
        });

        if (data.imagem != undefined) {
          if ($.trim(data.imagem) != "") {
            $(".pop").on("click", function () {
              $(".imagepreview").attr("src", data.imagem);
              $("#modal-image").modal("show");
            });
          }
        }

        //Apenas números input css
        $(".numericOnly").on("keypress keyup blur", function (event) {
          $(this).val(
            $(this)
              .val()
              .replace(/[^A-Z\.][^0-9\.]/g, "")
          );
          if (
            (event.which != 46 || $(this).val().indexOf(".") != -1) &&
            (event.which < 48 || event.which > 57)
          ) {
            event.preventDefault();
          }
        });

        if ($("#SEL_SimNao5").val() != undefined) {
          $("#SEL_SimNao5").change(function () {
            $("#divDistratoDocumento").hide();
            $("#divDistratoValorDevolvido").hide();
            $("#divDistratoNumeroParcelas").hide();
            $("#divDistratoVencimento").hide();

            if ($.trim($(this).val()) == strSim) {
              $("#divDistratoDocumento").show();
              $("#divDistratoValorDevolvido").show();
              $("#divDistratoNumeroParcelas").show();
              $("#divDistratoVencimento").show();
            }
          });

          $("#SEL_SimNao5").trigger("change");
        }

        if (data.orcamentos != undefined) {
          $("#ORC_ID2").change(function () {
            $("#OCI_ID2").html("");
            $("#OCI_ID2").append(
              "<option value=''>" + strSelecione + "</option>"
            );

            var valor = $.trim(this.value);

            if (valor != "") {
              $.ajax({
                url: $.trim($("#hddApropriacoesDados").val()),
                dataType: "json",
                cache: false,
                data: {
                  ORC_ID: valor,
                },
                type: "POST",
              })
                .success(function (data) {
                  $(".btn-formulario").prop("disabled", false);

                  if (data.error) {
                    dialogAlert(strAtencao, data.error.msg, 6);
                    return;
                  }

                  var strHtml = "";
                  for (var i = 0; i < data.arrDados.length; i++) {
                    strHtml += "<option ";

                    if (data.arrDados.length == 1) {
                      strHtml += " selected ";
                    }

                    strHtml +=
                      " value='" +
                      data.arrDados[i].OCI_ID +
                      "'>" +
                      data.arrDados[i].OCI_Codigo +
                      " - " +
                      data.arrDados[i].OCI_Descricao +
                      "</option>";
                  }

                  $("#OCI_ID2").append(strHtml);
                  $("#OCI_ID2").trigger("chosen:updated");
                })
                .fail(function (data) {
                  $(".btn-formulario").prop("disabled", false);
                  $("#OCI_ID2").trigger("chosen:updated");
                  dialogAlert(strAtencao, data.responseText, 6);
                });
            }
          });

          $("#OCI_ID2").change(function () {
            if ($.trim(this.value) != "") {
              $.post(
                $.trim(
                  $("#orcamentos_plano_financeiro_por_item_orcamento").val()
                ),
                {
                  ORC_ID: $("#ORC_ID2").val(),
                  OCI_ID: $.trim(this.value),
                },
                function (data) {
                  //alert(data); return;
                  if (data.sucesso == "true") {
                    $("#PLF_Conta2").val(data.PLF_Conta);
                  }

                  $("#PLF_Conta2").trigger("chosen:updated");
                },
                "json"
              );
            }
          });
        }

        if ($("#CPP_QuantidadeParcelas").val() != undefined) {
          $("#informacoes-parcelas").html(strCarregando);

          $("#CPP_QuantidadeParcelas").blur(function () {
            if ($.trim(this.value) != "") {
              $.ajax({
                url: $.trim(
                  $("#documentos_gerar_titulo_visualizar_parcelas").val()
                ),
                dataType: "json",
                cache: false,
                data: {
                  CPP_QuantidadeParcelas: $.trim(this.value),
                  DOC_ValorTotal: $.trim($("#DOC_ValorTotal").val()),
                  PRE_ID: $.trim($("#PRE_Gerar_ID").val()),
                },
                type: "POST",
              })
                .success(function (data) {
                  //alert(data); return;
                  if (data.error) {
                    $("#informacoes-parcelas").html("");
                    dialogAlert(strInformacao, data.error.msg, 6);
                    return;
                  }

                  $("#informacoes-parcelas").html(data.strHtml);
                  $(".maskMoney").maskMoney({
                    showSymbol: false,
                    symbol: "R$",
                    decimal: ",",
                    thousands: ".",
                    allowZero: true,
                    defaultZero: false,
                  });
                  return;
                })
                .fail(function (data) {
                  //alert(data); return;
                  $("#informacoes-parcelas").html("");
                  dialogAlert(strAtencao, data.responseText, 6);
                  return;
                });
            } else {
              $("#informacoes-parcelas").html("");
            }
          });

          $("#CPP_QuantidadeParcelas").trigger("blur");
        }
      }, 500);
    }).fail(function (data) {
      $("#btnConfirmPadraoYes").prop("disabled", false);
      $("#btnConfirmPadraoYes").html(strLabelSim);
      $("#dialogConfirmBootstrap").modal("hide");
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarCarteirasReajustes() {
  var strLabel = consultarPadraoInicial();
  var arrIndexadores = new Array();

  $("select[name='IND_ID[]'] option:selected").each(function () {
    arrIndexadores.push($(this).val());
  });

  $.ajax({
		url: $.trim($('#hddCarteirasReajustesConsultar').val()),
		dataType: 'json',
		cache: false,
		data: {
      IND_ID: arrIndexadores,
      REA_DataInicial: $.trim($("#txtDataInicial").val()),
      REA_DataFinal: $.trim($("#txtDataFinal").val()),
      CTO_Numero: $.trim($("#SGP_Numero").val()),
		},
		type: 'POST',
	}).success(function(data){
		if (data.error){
      consultarPadraoExcessao();
      consultarPadraoFalha(strLabel);
			dialogAlert(strAtencao, data.error.msg, 6);
			return;
		}

    consultarPadraoSucesso(strLabel);
    consultarPadraoSucessoPaginacao(data, true);

	}).fail(function(data){
    consultarPadraoExcessao();
    consultarPadraoFalha(strLabel);
		dialogAlert(strAtencao, data.responseText, 6);
	});

  /*$.post(
    $.trim($("#hddCarteirasReajustesConsultar").val()),
    {
      IND_ID: arrIndexadores,
      REA_DataInicial: $.trim($("#txtDataInicial").val()),
      REA_DataFinal: $.trim($("#txtDataFinal").val()),
      CTO_Numero: $.trim($("#SGP_Numero").val()),
    },
    function (data) {
      if (data.sucesso == "true") {
        consultarPadraoSucesso(strLabel);
        consultarPadraoSucessoPaginacao(data, true);
      } else {
        consultarPadraoExcessao();
        consultarPadraoFalha(strLabel);

        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );*/
}

function salvarGruposEmpresasRapido() {
  if ($.trim($("#GRE_DescricaoGERapido").val()) == "") {
    $.notify("Grupo de empresa precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#GRE_ApelidoGERapido").val()) == "") {
    $.notify("Apelido precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#GRE_QuantidadeContasGERapido").val()) == "") {
    $.notify(
      "Quantidade de contas do grupo de empresa precisa ser informado.",
      "warn"
    );
    return;
  } else if ($.trim($("#USU_NomeGERapido").val()) == "") {
    $.notify("Nome do usuário precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#USU_EmailGERapido").val()) == "") {
    $.notify("E-mail do usuário precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#PER_DescricaoGERapido").val()) == "") {
    $.notify("Perfil do usuário precisa ser informado.", "warn");
    return;
  } else {
    //Verifica se selecionou no minimo uma ação do módulo (obrigatório)
    var arrAcoes = new Array();
    $("input[type=checkbox][name='chkAcoesGERapido[]']:checked").each(
      function () {
        arrAcoes.push($(this).val());
      }
    );

    if (arrAcoes.length > 0) {
      $("#btnSalvarGERapido, #btnCancelarGERapido").prop("disabled", true);
      var strLabel = $("#btnSalvarGERapido").html();
      $("#btnSalvarGERapido").html(strCarregando);

      var arrEstados = new Array();
      var arrCidades = new Array();
      var arrTipos = new Array();

      $("select[name='UF_IDGERapido[]'] option:selected").each(function () {
        arrEstados.push($(this).val());
      });

      $("select[name='CID_IDGERapido[]'] option:selected").each(function () {
        arrCidades.push($(this).val());
      });

      $("select[name='CAG_IDGERapido[]'] option:selected").each(function () {
        arrTipos.push($(this).val());
      });

      var arrDadosFormulario = new FormData();

      arrDadosFormulario.append(
        "GRE_Descricao",
        $("#GRE_DescricaoGERapido").val()
      );
      arrDadosFormulario.append("GRE_Apelido", $("#GRE_ApelidoGERapido").val());
      arrDadosFormulario.append(
        "GRE_QuantidadeContas",
        $.trim($("#GRE_QuantidadeContasGERapido").val())
      );
      arrDadosFormulario.append(
        "GRE_Imagem",
        $("#GRE_ImagemGERapido").prop("files")[0]
      );
      arrDadosFormulario.append(
        "USU_Nome",
        $.trim($("#USU_NomeGERapido").val())
      );
      arrDadosFormulario.append(
        "USU_Email",
        $.trim($("#USU_EmailGERapido").val())
      );
      arrDadosFormulario.append(
        "USU_Telefone",
        $.trim($("#USU_TelefoneGERapido").val())
      );
      arrDadosFormulario.append(
        "USU_Celular",
        $.trim($("#USU_CelularGERapido").val())
      );
      arrDadosFormulario.append(
        "USU_DataNascimento",
        $.trim($("#USU_DataNascimentoGERapido").val())
      );
      arrDadosFormulario.append(
        "USU_Imagem",
        $("#USU_ImagemGERapido").prop("files")[0]
      );
      arrDadosFormulario.append(
        "PER_Descricao",
        $.trim($("#PER_DescricaoGERapido").val())
      );

      for (var i = 0; i < arrAcoes.length; i++) {
        arrDadosFormulario.append("ACO_ID[]", arrAcoes[i]);
      }

      var strSelecionado = strNao;
      var strRestrigeEmpreendimentos = strNao;
      var strImportarInsumos = strNao;
      var strPlanoFinanceiroHiperdados = strNao;
      var strComproTerrenoImportarApoioTarefas = strNao;
      var strComproTerrenoImportarProporcionais = strNao;
      var strComproTerrenoImportarApoioCurvas = strNao;
      var strComproTerrenoImportarApoioPeriodicos = strNao;

      if ($("#GRE_RestringeCidadeGERapido").is(":checked"))
        strSelecionado = strSim;
      if ($("#GRE_ImportarInsumosGERapido").is(":checked"))
        strImportarInsumos = strSim;
      if ($("#GRE_RestringeEmpreendimentosGERapido").is(":checked"))
        strRestrigeEmpreendimentos = strSim;
      if ($("#GRE_ComproTerrenoImportarApoioTarefasGERapido").is(":checked"))
        strComproTerrenoImportarApoioTarefas = strSim;
      if ($("#GRE_ComproTerrenoImportarProporcionaisGERapido").is(":checked"))
        strComproTerrenoImportarProporcionais = strSim;
      if ($("#GRE_ComproTerrenoImportarApoioCurvasGERapido").is(":checked"))
        strComproTerrenoImportarApoioCurvas = strSim;
      if ($("#GRE_ComproTerrenoImportarApoioPeriodicosGERapido").is(":checked"))
        strComproTerrenoImportarApoioPeriodicos = strSim;
      if ($("#GRE_ImportarPlanoFinanceiroGERapido").is(":checked"))
        strPlanoFinanceiroHiperdados = strSim;

      if ($.trim(strSelecionado) == strSim && arrEstados.length == 0) {
        $.notify("Estado é obrigatórios.", "warn");

        $("#btnSalvarGERapido, #btnCancelarGERapido").prop("disabled", false);
        $("#btnSalvarGERapido").html(strLabel);
        return;
      }

      arrDadosFormulario.append("GRE_RestringeCidade", $.trim(strSelecionado));
      arrDadosFormulario.append(
        "GRE_RestringeEmpreendimentos",
        $.trim(strRestrigeEmpreendimentos)
      );
      arrDadosFormulario.append(
        "GRE_ComproTerrenoImportarApoioTarefas",
        $.trim(strComproTerrenoImportarApoioTarefas)
      );
      arrDadosFormulario.append(
        "GRE_ComproTerrenoImportarProporcionais",
        $.trim(strComproTerrenoImportarProporcionais)
      );
      arrDadosFormulario.append(
        "GRE_ComproTerrenoImportarApoioCurvas",
        $.trim(strComproTerrenoImportarApoioCurvas)
      );
      arrDadosFormulario.append(
        "GRE_ComproTerrenoImportarApoioPeriodicos",
        $.trim(strComproTerrenoImportarApoioPeriodicos)
      );
      arrDadosFormulario.append(
        "GRE_ImportarInsumos",
        $.trim(strImportarInsumos)
      );
      arrDadosFormulario.append(
        "GRE_ImportarPlanoFinanceiro",
        $.trim(strPlanoFinanceiroHiperdados)
      );

      if (
        $("#GRE_ImportarLayoutPlanosFinaneirosGERapido").prop("files") !=
        undefined
      ) {
        if (
          $("#GRE_ImportarLayoutPlanosFinaneirosGERapido").prop("files")[0] !=
          undefined
        ) {
          arrDadosFormulario.append(
            "GRE_ImportarLayoutPlanosFinaneiros",
            $("#GRE_ImportarLayoutPlanosFinaneirosGERapido").prop("files")[0]
          );
        }
      }

      if (
        $("#GRE_ImportarOrcamentosComposicoesGERapido").prop("files") !=
        undefined
      ) {
        if (
          $("#GRE_ImportarOrcamentosComposicoesGERapido").prop("files")[0] !=
          undefined
        ) {
          arrDadosFormulario.append(
            "GRE_ImportarOrcamentosComposicoes",
            $("#GRE_ImportarOrcamentosComposicoesGERapido").prop("files")[0]
          );
        }
      }

      for (var intI = 0; intI < arrEstados.length; intI++) {
        arrDadosFormulario.append("UF_ID[]", $.trim(arrEstados[intI]));
      }

      for (var intI = 0; intI < arrCidades.length; intI++) {
        arrDadosFormulario.append("CID_ID[]", $.trim(arrCidades[intI]));
      }

      for (var intI = 0; intI < arrTipos.length; intI++) {
        arrDadosFormulario.append("CAG_ID[]", $.trim(arrTipos[intI]));
      }

      $.ajax({
        url: $.trim($("#hddGruposEmpresasSalvarRapido").val()),
        dataType: "json",
        cache: false,
        contentType: false,
        processData: false,
        data: arrDadosFormulario,
        type: "post",
        success: function (data) {
          $("#btnSalvarGERapido, #btnCancelarGERapido").prop("disabled", false);
          $("#btnSalvarGERapido").html(strLabel);

          if (data.error) {
            dialogAlert(strAtencao, data.error.msg, 6);
            return;
          }

          $.notify(data.mensagem, "success");

          $("#btnSalvarGERapido, #btnCancelarGERapido").prop("disabled", false);
          $("#btnCancelarGERapido").trigger("click");
        },
      }).fail(function (data) {
        $("#btnSalvarGERapido, #btnCancelarGERapido").prop("disabled", false);
        $("#btnSalvarGERapido").html(strLabel);

        dialogAlert(strAtencao, data.responseText, 6);
      });
    } else {
      $.notify("No mínimo uma ação do módulo deve ser selecionada.", "warn");
      return;
    }
  }
}

function esqueciSenhaContadores() {
  $.post(
    $.trim($("#hddEsqueciSenhaContabilidade").val()),
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(
          "Portal da Contabilidade - Esqueci senha ",
          data.strHtml,
          3
        );

        setTimeout(function () {
          $(".modal-header").addClass("bg-maroon");
          preLoadingClose();
        }, 200);
      }
      return;
    },
    "json"
  );
}

function enterEsqueciSenhaContadores(e) {
  if (e.keyCode == 13) {
    enviarEsqueciSenhaContadores();
  }
}

function enviarEsqueciSenhaContadores() {
  if ($.trim($("#USU_Login2").val()) == "") {
    $.notify("E-mail precisa ser informado.", "warn");
    return;
  } else {
    $("#USU_Login2").prop("disabled", true);

    $.post(
      $.trim($("#hddEsqueciSenhaEnvioContabilidade").val()),
      { USU_Email: $.trim($("#USU_Login2").val()) },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");
        } else {
          $.notify(data.mensagem, "error");
        }
        $(".modal").modal("hide");
        $("#USU_Login2").prop("disabled", false);
        return;
      },
      "json"
    );
  }
}

function enterValidarNovaSenhaContador(e) {
  if (e.keyCode == 13) {
    validarNovaSenhaContador();
  }
}

function validarNovaSenhaContador() {
  if ($.trim($("#USU_Senha").val()) == "") {
    $.notify("Senha precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#USU_Senha2").val()) == "") {
    $.notify("Confirmação da senha precisa ser informada.", "warn");
    return;
  } else if ($("#USU_Senha").val() != $("#USU_Senha2").val()) {
    $.notify("Senhas precisam ser iguais.", "warn");
    return;
  } else {
    $("#btnEnviar").prop("disabled", true);

    $.post(
      $.trim($("#hddEsqueciAtualizarContador").val()),
      {
        USU_Cript: $.trim($("#hddCript").val()),
        USU_Senha: $.trim($("#USU_Senha").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $.notify(data.mensagem, "success");

          setTimeout(function () {
            redir(data.redir, "parent");
          }, 2000);
        }
      },
      "json"
    );
  }
}

function formularioReportar(empreendimentoID, empreendimentoDescricao) {
  var strHtml =
    "<form class='form-horizontal' target='_blank' id='frmFormularioDialog' method='post' onSubmit='return false;' enctype='multipart/form-data'>";
  strHtml +=
    "Observações: <textarea style='width:475px;' class='form-control' rows='3' name='ERE_Observacoes' id='ERE_Observacoes' placeholder='Informe aqui a observação'></textarea>";
  strHtml +=
    "<button style='position:absolute;top:78px;right:30px;' type='button' id='btnReportarEmpreendimento' class='btn btn-sm btn-danger pull-right'>";
  strHtml += "<i class='glyphicon glyphicon-send'></i> Enviar";
  strHtml += "</button>";
  strHtml += "</form>";
  dialogAlert2(
    "<i class='glyphicon glyphicon-bullhorn'></i> Reportar: <b>" +
    empreendimentoDescricao +
    "</b>",
    strHtml,
    6
  );

  setTimeout(function () {
    $("#btnReportarEmpreendimento").click(function (e) {
      if ($.trim($("#ERE_Observacoes").val()) == "") {
        $.notify("Observação precisa ser informada.", "warn");
        return;
      } else {
        $("#btnReportarEmpreendimento").prop("disabled", true);
        $("#btnReportarEmpreendimento").html(strCarregando);

        $.post(
          $.trim($("#empreendimentos_reportar_enviar").val()),
          {
            CHD_ID: $.trim(empreendimentoID),
            CHD_Descricao: $.trim(empreendimentoDescricao),
            ERE_Observacoes: $.trim($("#ERE_Observacoes").val()),
          },
          function (data) {
            //alert(data); return;
            if (data.sucesso == "true") {
              $.notify(data.mensagem, "success");
            } else {
              $.notify(data.mensagem, "error");
            }

            $("#btnReportarEmpreendimento").prop("disabled", false);
            $("#btnReportarEmpreendimento").html(
              "<i class='glyphicon glyphicon-send'></i> Enviar"
            );
            $(".modal").modal("hide");
            return;
          },
          "json"
        );
      }
    });
  }, 500);
}

function addSelectItem(t, ev, nivel) {
  ev.stopPropagation();

  var txt = $.trim($(t).prev().val().replace(/[|]/g, ""));

  if ($.trim(txt) != "") {
    $("#" + nivel).append(
      "<option value='" + txt + "' selected>" + txt + "</option>"
    );
    $(t).closest(".bootstrap-select").prev().selectpicker("refresh");
  }
}

function addSelectInpKeyPress(t, ev, nivel) {
  ev.stopPropagation();
  if (ev.which == 124) ev.preventDefault(); // do not allow pipe character
  if (ev.which == 13) {
    // enter character adds the option
    ev.preventDefault();
    addSelectItem($(t).next(), ev, nivel);
  }
}

function salvarRotasRapido() {
  if ($.trim($("#ROT_Nivel1").val()) == "") {
    $.notify("Nível 1 da rota precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#ROT_Caminho").val()) == "") {
    $.notify("Caminho da rota precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SIM_Nome").val()) == "") {
    $.notify("Icone da rota precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#ROT_Ordem").val()) == "") {
    $.notify("Ordem da rota precisa ser informado.", "warn");
    return;
  } else {
    var arrModulos = new Array();
    var arrAcoes = new Array();
    var arrPerfis = new Array();

    $("input[type=checkbox][name='MOD_ID[]']:checked").each(function () {
      arrModulos.push($(this).val());
    });

    $("select[name='ACO_ID[]'] option:selected").each(function () {
      arrAcoes.push($(this).val());
    });

    $("input[type=checkbox][name='PER_ID[]']:checked").each(function () {
      arrPerfis.push($(this).val());
    });

    if (arrModulos.length == 0) {
      $.notify("No minímo 1 (UM) módulo deve ser selecionada.", "warn");
      return;
    } else if (arrAcoes.length == 0) {
      $.notify("No minímo 1 (UMA) ação deve ser selecionada.", "warn");
      return;
    } else if (arrPerfis.length == 0) {
      $.notify("No minímo 1 (UM) perfil deve ser selecionado.", "warn");
      return;
    } else {
      $("#btnCancelarRotas, #btnSalvarRotas").prop("disabled", true);
      $("#btnSalvarRotas").html(strCarregando);

      $.ajax({
        url: $.trim($("#rotas_adicionar_salvar").val()),
        dataType: "json",
        cache: false,
        data: {
          MOD_ID: arrModulos,
          ACO_ID: arrAcoes,
          PER_ID: arrPerfis,
          ROT_Nivel1: $.trim($("#ROT_Nivel1").val()),
          ROT_Nivel2: $.trim($("#ROT_Nivel2").val()),
          ROT_Nivel3: $.trim($("#ROT_Nivel3").val()),
          ROT_Caminho: $.trim($("#ROT_Caminho").val()),
          ROT_IconeClass: $.trim($("#SIM_Nome").val()),
          ROT_LinkVideo: $.trim($("#ROT_LinkVideo").val()),
          ROT_Ordem: $.trim($("#ROT_Ordem").val()),
        },
        type: "POST",
      })
        .success(function (data) {
          //alert(data); return;
          if (data.sucesso == "true") {
            $.notify(data.mensagem, "success");
          } else {
            $.notify(data.mensagem, "error");
          }

          $("#btnCancelarRotas, #btnSalvarRotas").prop("disabled", false);
          $("#btnSalvarRotas").html($.trim($("hddLabelBtnCadastrar").val()));
          $("#btnCancelarRotas").trigger("click");
          return;
        })
        .fail(function (data) {
          $(".modal").modal("hide");
          dialogAlert(strAtencao, data.responseText, 6);
        });
    }
  }
}

function pesquisarCEPDinamico(
  valor,
  inputEndereco,
  inputUF,
  inputCidade,
  inputBairro,
  inputNumero,
  inputComplemento
) {
  if (valor.length == 8) {
    $("#" + inputEndereco).val("");
    //$('#'+inputUF).val('');
    $("#" + inputCidade).val("");
    $("#" + inputBairro).val("");
    $("#" + inputNumero).val("");
    $("#" + inputComplemento).val("");

    $.post(
      $.trim($("#hddPesquisarCEP").val()),
      { CEP: valor },
      function (data) {
        console.log('aaaaa', data); 
        if (data.sucesso == "true") {
          $("#" + inputEndereco).val(data.arrDados[0].END_EnderecoCompleto);
          $("#" + inputUF).val(data.arrDadosCidades.UF_ID);

          if ($.trim(data.arrDados[0].CID_ID) != "") {
            carregarCidadesDinamico(
              inputCidade,
              data.arrDadosCidades.UF_ID,
              data.arrDados[0].CID_ID
            );
          }

          if ($.trim(data.arrDados[0].BAI_ID) != "") {
            console.log('xxxxx', data); 

            carregarBairrosDinamico(
              inputBairro,
              data.arrDadosCidades.CID_ID,
              data.arrDados[0].BAI_ID
            );
          }
        }

        $("#" + inputUF + "").trigger("chosen:updated");
      },
      "json"
    );
  }
}

function carregarCidadesDinamico(inputCidade, estadoID, cidadeSelecionada) {
  $.post($.trim($("#hddPesquisarCidades").val()), {
      UF_ID: estadoID,
      CID_ID: cidadeSelecionada,
  },
    function (data) {
      // console.log('qqqqq', data); 
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          $("#" + inputCidade).val(data.arrDados[0]["CID_Descricao"]);
        }
      }
    },
    "json"
  );
}

function carregarBairrosDinamico(inputBairro, cidadeID, bairroSelecionado) {
  $.post(
    $.trim($("#hddPesquisarBairros").val()),
    {
      CID_ID: cidadeID,
      BAI_ID: bairroSelecionado,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        if (data.arrDados.length > 0) {
          $("#" + inputBairro).val(data.arrDados[0]["BAI_Descricao"]);
        }
      }
    },
    "json"
  );
}

function salvarPlanosContabeis() {
  if ($.trim($("#PLC_Conta").val()) == "") {
    $.notify("Conta precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#PLC_Natureza").val()) == "") {
    $.notify("Natureza da conta precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#PLC_Descricao").val()) == "") {
    $.notify("Descrição da conta precisa ser informada.", "warn");
    return;
  } else {
    $("button").prop("disabled", true);

    $.ajax({
      url: $.trim($("#contadores_planos_contabeis_pai_filho_add").val()),
      dataType: "json",
      cache: false,
      data: {
        PLC_Conta: $.trim($("#PLC_Conta").val()),
        PLC_Natureza: $.trim($("#PLC_Natureza").val()),
        PLC_Descricao: $.trim($("#PLC_Descricao").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        //alert(data); return;
        $(".modal").modal("hide");
        $.notify(data.mensagem, "success");

        setTimeout(function () {
          redir("", "parent");
        }, 1500);

        $("button").prop("disabled", false);
        return;
      })
      .fail(function (data) {
        $(".modal").modal("hide");
        dialogAlert(strAtencao, data.responseText, 6);
        $("button").prop("disabled", false);
        return;
      });
  }
}

function atualizaPlanoFinanceiroContadores(rota, valor, campo) {
  $.ajax({
    url: $.trim(rota + valor),
    dataType: "json",
    cache: false,
    data: {
      CAMPO: $.trim(campo),
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;

      $(".modal").modal("hide");
      $.notify(data.mensagem, "success");
      return;
    })
    .fail(function (data) {
      $(".modal").modal("hide");
      dialogAlert(strAtencao, data.responseText, 6);
      $("button").prop("disabled", false);
      return;
    });
}

function consultarVersoes() {
  $("#cntConsultaVersoes").html(strCarregando);

  $.ajax({
    url: $.trim($("#versoes_sistema_consultar").val()),
    dataType: "json",
    cache: false,
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      $("#cntConsultaVersoes").html(data.strHtml);

      if (data.totalRegistros > 0) {
        setTimeout(function () {
          requireDataTablesDialog(true, false);
        }, 500);
      }

      return;
    })
    .fail(function (data) {
      $(".modal").modal("hide");
      dialogAlert(strAtencao, data.responseText, 6);
      $("button").prop("disabled", false);
      return;
    });
}

function salvarVersaoSistema() {
  if ($.trim($("#VSS_Numero").val()) == "") {
    $.notify("Número da versão precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#VSS_Descricao").val()) == "") {
    $.notify("Descrição da versão precisa ser informado.", "warn");
    return;
  } else {
    $("button").prop("disabled", true);

    var strAtual = strNao;
    if ($("#VSS_VersaoAtual").is(":checked")) strAtual = strSim;

    $.ajax({
      url: $.trim($("#versoes_sistema_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        VSS_ID: $.trim($("#VSS_ID").val()),
        VSS_Numero: $.trim($("#VSS_Numero").val()),
        VSS_Descricao: $("iframe").contents().find(".wysihtml5-editor").html(),
        VSS_VersaoAtual: strAtual,
      },
      type: "POST",
    })
      .success(function (data) {
        //alert(data); return;
        $("button").prop("disabled", false);
        $(".modal").modal("hide");

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");
        limparVersaoSistema();
        return;
      })
      .fail(function (data) {
        //alert(data); return;
        //$('.modal').modal('hide');
        dialogAlert(strAtencao, data.responseText, 6);
        $("button").prop("disabled", false);
        return;
      });
  }
}

function limparVersaoSistema() {
  $("#VSS_VersaoAtual").prop("checked", false);
  $("#VSS_ID").val("");
  $("#VSS_Numero").val("");
  $("iframe").contents().find(".wysihtml5-editor").html("");
}

function editarVersaoSistema(versaoID) {
  $.ajax({
    url: $.trim($("#versoes_sistema_editar").val()) + "/" + versaoID,
    dataType: "json",
    cache: false,
    data: {
      VSS_ID: $.trim(versaoID),
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      $("button").prop("disabled", false);

      $("#VSS_ID").val(data.arrDados["VSS_ID"]);
      $("#VSS_Numero").val(data.arrDados["VSS_Numero"]);
      $("iframe")
        .contents()
        .find(".wysihtml5-editor")
        .html(data.arrDados["VSS_Descricao"]);

      if (data.arrDados["VSS_VersaoAtual"] == strSim) {
        $("#VSS_VersaoAtual").prop("checked", true);
      } else {
        $("#VSS_VersaoAtual").prop("checked", false);
      }
      return;
    })
    .fail(function (data) {
      //alert(data); return;
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function versaoSGP() {
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#versoes_sistema_visualizar").val()),
    dataType: "json",
    cache: false,
    type: "POST",
  })
    .success(function (data) {
      dialogAlert2(data.strTitulo, data.strHtml, 3);
      preLoadingClose();
      return;
    })
    .fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
      preLoadingClose();
      return;
    });
}

function consultarExtratosClientes() {
  preLoadingOpen();
  $("#consultar-dados").html(strCarregando);

  var arrEmpresas = new Array();
  var arrContas = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='CON_ID[]'] option:selected").each(function () {
    arrContas.push($(this).val());
  });

  $.post(
    $.trim($("#hddExtratosConsultar").val()),
    {
      EMP_ID: arrEmpresas,
      CON_ID: arrContas,
      DataCadastroInicial: $("#txtDataInicial").val(),
      DataCadastroFinal: $("#txtDataFinal").val(),
      DataConciliadoInicial: $("#txtDataConciliadoInicial").val(),
      DataConciliadoFinal: $("#txtDataConciliadoFinal").val(),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#consultar-dados").html(data.strHtml);

        if (data.totalRegistros > 0) {
          requireDataTables(false, true, true, true, true, false, true);
        }
      } else {
        $("#consultar-dados").html("");
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
    },
    "json"
  );
}

function consultarEmpreendimentosCliente() {
  preLoadingOpen();
  var arrEstruturas = new Array();

  $("select[name='EST_ID[]'] option:selected").each(function () {
    arrEstruturas.push($(this).val());
  });

  $.post(
    $.trim($("#portal_cliente_empreendimentos_clientes_consultar").val()),
    {
      EST_ID: arrEstruturas,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#consultar-dados").html(data.strHtml);
      }
      preLoadingClose();
    },
    "json"
  );
}

function dialogRotas() {
  preLoadingOpen();

  $.post(
    $.trim($("#atalhos_consultar").val()),
    function (data) {
      //alert(data); return;
      if (data.strTitulo !== undefined) {
        dialogAlert2(data.strTitulo, data.strHtml, 3);
      }

      preLoadingClose();
      return;
    },
    "json"
  );
}

function editarCotacoesFornecedoresObservacoes(fornecedorID, fornecedorNome) {
  preLoadingOpen();

  $.post(
    $.trim($("#cotacoes_observacoes_fornecedor_editar").val()),
    {
      COT_ID: $.trim($("#COT_ID").val()),
      ENT_ID: $.trim(fornecedorID),
      ENT_NomeFantasia: $.trim(fornecedorNome),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          setInitFunctions();
        }, 1000);
      } else {
        $.notify(data.mensagem, "error");
      }

      preLoadingClose();
      return;
    },
    "json"
  );
}

function salvarCotacoesFornecedoresObservacoes() {
  $("#btnSalvarObservacoes").prop("disabled", true);
  var strLabel = $("#btnSalvarObservacoes").html();
  $("#btnSalvarObservacoes").html(strCarregando);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#cotacoes_observacoes_fornecedor_salvar").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      COT_ID: $.trim($("#COT_ID").val()),
      ENT_ID: $.trim($("#fornecedorID").val()),
      CFO_PrazoPagamento: $.trim($("#CFO_PrazoPagamento").val()),
      COP_ID: $.trim($("#COP_ID").val()),
      CFO_DataVencimento: $.trim($("#CFO_DataVencimento").val()),
      CFO_ValorFrete: $.trim($("#CFO_ValorFrete").val()),
      CFO_ValorDesconto: $.trim($("#CFO_ValorDesconto").val()),
      CFO_Observacoes: $.trim($("#CFO_Observacoes").val())
    },
  }).success(function (data) {
    $("#btnSalvarObservacoes").prop("disabled", false);
    $("#btnSalvarObservacoes").html(strLabel);
    preLoadingClose();

    if (data.error) {
      dialogAlert(strAtencao, data.error.msg, 6);
      return;
    }

    $("#fornecedorID, #CFO_PrazoPagamento, #COP_ID, #CFO_DataVencimento, #CFO_Observacoes").val("");
    $("#btnAtualizar").trigger("click");
    fecharModal();

  }).fail(function (data) {
    $(".btn-filtro, .btn-formulario").prop("disbled", false);
    dialogAlert(strAtencao, data.responseText, 6);
  });
}

function atualizarItensPedidosEstoque(itemPedidoID, input) {
  $(".btn-formulario").prop("disabled", true);
  preLoadingClose();

  var bolMarcado = false;
  if ($(input).is(":checked")) {
    bolMarcado = true;
  }

  $.ajax({
    url: $.trim($("#pedidos_atualiza_estoque").val()),
    dataType: "json",
    cache: false,
    data: {
      PED_ID: $.trim($("#PED_ID").val()),
      PDI_ID: $.trim(itemPedidoID),
      PED_Marcado: bolMarcado,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $.notify(data.mensagem, "success");
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function atualizarItensMedicoesEstoque(itemMedicaoID, input) {
  var strEstoque = strNao;
  if ($(input).is(":checked")) {
    strEstoque = strSim;
  }

  $.post(
    $.trim($("#contratos_medicoes_atualizar_estoque").val()),
    {
      MED_ID: $.trim($("#hddCodigoSelecionado").val()),
      MEI_ID: $.trim(itemMedicaoID),
      MEI_Estoque: $.trim(strEstoque),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $.notify(data.mensagem, "success");
      } else {
        $.notify(data.mensagem, "error");
      }

      consultarMedicoes();
      return;
    },
    "json"
  );
}

function enterPesquisarComprasEstoques(e) {
  if (e.keyCode == 13) {
    if (
      $.trim($("#PED_Numero").val()) != "" ||
      $.trim($("#CON_Numero3").val()) != "" ||
      $.trim($("#MED_Codigo").val()) != ""
    ) {
      $("#txtDataInicial, #txtDataFinal").val("");
    }

    consultarComprasEstoque();
  }
}

function consultarComprasEstoque() {
  var strLabel = consultarPadraoInicial();
  var arrEmpresas = new Array();
  var arrFornecedores = new Array();
  var arrInsumos = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='ENT_ID[]'] option:selected").each(function () {
    arrFornecedores.push($(this).val());
  });

  $("select[name='INS_ID[]'] option:selected").each(function () {
    arrInsumos.push($(this).val());
  });

  var strEstoque = strNao;

  if ($("#EST_SaldoEstoque").is(":checked")) strEstoque = strSim;

  $.post(
    $.trim($("#compras_estoques_consultar").val()),
    {
      EMP_ID: arrEmpresas,
      ENT_ID: arrFornecedores,
      INS_ID: arrInsumos,
      PED_Numero: $.trim($("#PED_Numero").val()),
      CON_Numero: $.trim($("#CON_Numero3").val()),
      MED_Codigo: $.trim($("#MED_Codigo").val()),
      EST_Pesquisar: $.trim($("#EST_Pesquisar").val()),
      EST_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      EST_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
      EST_SaldoEstoque: $.trim(strEstoque),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        consultarPadraoSucesso(strLabel);
        consultarPadraoSucessoPaginacao(data, true);
      } else {
        consultarPadraoExcessao();
        consultarPadraoFalha(strLabel);
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function adicionarComprasEstoques(
  strFlag,
  strTipo,
  strFornecedor,
  strInsumo,
  strNumero,
  ID,
  douQuantidade,
  douSaldo,
  douEstoque,
  douValorUnitario,
  douValorTotal
) {
  $("#spnSaldoEstoque").html(strCarregando);

  $.post(
    $.trim($("#compras_estoques_novo").val()),
    {
      TIPO: strFlag,
      TIPO_DESCRICAO: strTipo,
      FORNECEDOR: strFornecedor,
      INSUMO: strInsumo,
      NUMERO: strNumero,
      ID: ID,
      QUANTIDADE: douQuantidade,
      SALDO: douSaldo,
      ESTOQUE: douEstoque,
      VALORUNITARIO: douValorUnitario,
      VALORTOTAL: douValorTotal,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          $("#QUANTIDADE").blur(function (e) {
            $.ajax({
              url: $.trim($("#compras_estoques_saldo").val()),
              dataType: "json",
              cache: false,
              data: {
                EST_Quantidade: $.trim($(this).val()),
                EST_Tipo: strFlag,
                ID: ID,
              },
              type: "POST",
            })
              .success(function (data) {
                //alert(data); return;
                if ($.trim(data.mensagem) != "") {
                  $.notify(data.mensagem, "error");
                } else {
                  //$('button').prop('disabled', false);
                }

                $("#spnSaldoEstoque").html(data.douValor);
                return;
              })
              .fail(function (data) {
                $("#spnSaldoEstoque").html($("#spnQuantidade").html());
                $.notify(data.responseText, "error");
                return;
              });
          });

          $("#btnSalvarEstoque").click(function (e) {
            if (
              $.trim($("#QUANTIDADE").val()) == "" ||
              $.trim($("#QUANTIDADE").val()) == 0
            ) {
              $.notify("Quantidade precisa ser informada.", "warn");
              return;
            } else if (parseFloat($("#spnSaldoEstoque").html()) >= 0) {
              //$('button').prop('disabled', true);
              //$('#spnQuantidade').html(strCarregando);

              $.ajax({
                url: $.trim($("#compras_estoques_salvar").val()),
                dataType: "json",
                cache: false,
                data: {
                  ID: ID,
                  QUANTIDADE: $.trim($("#QUANTIDADE").val()),
                  EST_Tipo: $.trim(strFlag),
                },
                type: "POST",
              })
                .success(function (data) {
                  //alert(data); return;
                  //$('#spnQuantidade').html(data.douValor);
                  $.notify(data.mensagem, "success");
                  consultarEstoqueAdicionados(strFlag, ID);
                  consultarComprasEstoque();
                  $("#QUANTIDADE").val("");
                  return;
                })
                .fail(function (data) {
                  //alert(data); return;
                  dialogAlert(strAtencao, data.responseText, 6);
                  return;
                });
            } else {
              $.notify("Saldo indisponível", "warn");
              return;
            }
          });

          $("#QUANTIDADE").trigger("blur");

          consultarEstoqueAdicionados(strFlag, ID);
        }, 500);
      } else {
        $.notify(data.mensagem, "error");
      }
      return;
    },
    "json"
  );
}

function consultarEstoqueAdicionados(strFlag, ID) {
  preLoadingOpen();
  $("#consultar-estoques").html(strCarregando);

  $.post(
    $.trim($("#compras_estoques_consultar_item").val()),
    {
      EST_Tipo: strFlag,
      ID: ID,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#consultar-estoques").html(data.strHtml);
      } else {
        $("#consultar-estoques").html("");
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function confirmarExcluirEstoque(
  strRota,
  strTitulo,
  strMensagem,
  strTipo,
  strTipoItem,
  itemID
) {
  var types = BootstrapDialog.TYPE_DEFAULT;
  var btnCSS = "btn-default";

  if (strTipo == strBootstrapCodigoInfo) {
    //2
    types = BootstrapDialog.TYPE_INFO;
    btnCSS = "btn-info";
  } else if (strTipo == strBootstrapCodigoPrimary) {
    //3
    types = BootstrapDialog.TYPE_PRIMARY;
    btnCSS = "btn-primary";
  } else if (strTipo == strBootstrapCodigoSuccess) {
    //4
    types = BootstrapDialog.TYPE_SUCCESS;
    btnCSS = "btn-success";
  } else if (strTipo == strBootstrapCodigoWarning) {
    //5
    types = BootstrapDialog.TYPE_WARNING;
    btnCSS = "btn-warning";
  } else if (strTipo == strBootstrapCodigoDanger) {
    //6
    types = BootstrapDialog.TYPE_DANGER;
    btnCSS = "btn-danger";
  }

  BootstrapDialog.show({
    title: strTitulo,
    message: strMensagem,
    size: BootstrapDialog.SIZE_WIDE,
    type: types,
    id: "dialogExcluirModal",
    buttons: [
      {
        label: strLabelNao,
        action: function (dialogItself) {
          dialogItself.close();
        },
      },
      {
        label: strLabelSim,
        cssClass: btnCSS,
        data: {
          js: "btn-confirm",
          "user-id": "3",
        },
        id: "btnConfirmPadraoYes",
        action: function () {
          $("#btnConfirmPadraoYes").prop("disabled", true);
          $("#btnConfirmPadraoYes").html(strCarregando);

          $.ajax({
            url: $.trim(strRota),
            dataType: "json",
            cache: false,
            data: {
              valor: "1",
            },
            type: "POST",
          })
            .success(function (data) {
              $.notify(data.mensagem, "success");

              consultarComprasEstoque();
              $("#btnConfirmPadraoYes").prop("disabled", false);
              $("#btnConfirmPadraoYes").html(strLabelSim);
              $(".modal").modal("hide");
              return;
            })
            .fail(function (data) {
              //alert(data); return;
              dialogAlert(strAtencao, data.responseText, 6);

              $("#btnConfirmPadraoYes").prop("disabled", false);
              $("#btnConfirmPadraoYes").html(strLabelSim);
              $(".modal").modal("hide");
              return;
            });
        },
      },
    ],
  });
}

function consultarAprovacoesCompras() {
  if ($.trim($("#APR_Numero").val()) != "" && $.trim($("#APR_Tipo").val()) == ""){
      $.notify("Tipo da aprovação precisa ser selecionada.", "warn");
      return;
  }else{
      var strLabel        = consultarPadraoInicial();
      var arrEmpresas     = new Array();
      var arrUsuarios     = new Array();
      var arrEntidades    = new Array();
      var arrCentroCustos = new Array();
      var arrObras        = new Array();

      $("select[name='EMP_ID[]'] option:selected").each(function () {
        arrEmpresas.push($(this).val());
      });

      $("select[name='USU_ID[]'] option:selected").each(function () {
        arrUsuarios.push($(this).val());
      });

      $("select[name='ENT_ID[]'] option:selected").each(function () {
        arrEntidades.push($(this).val());
      });

      $("select[name='CEN_ID[]'] option:selected").each(function () {
        arrCentroCustos.push($(this).val());
      });

      $("select[name='CAX_Obra_ID[]'] option:selected").each(function () {
        arrObras.push($(this).val());
      });

      $.ajax({
        url: $.trim($("#compras_aprovacoes_consultar").val()),
        dataType: "json",
        cache: false,
        data: {
          EMP_ID: arrEmpresas,
          USU_Cadastro_ID: arrUsuarios,
          ENT_ID: arrEntidades,
          CEN_ID: arrCentroCustos,
          CAX_Obra_ID: arrObras,
          APR_Tipo: $.trim($("#APR_Tipo").val()),
          APR_Numero: $.trim($("#APR_Numero").val()),
          DATAINICIAL: $.trim($("#txtDataInicial").val()),
          DATAFINAL: $.trim($("#txtDataFinal").val()),
        },
        type: "POST",
      }).success(function (data) {
          consultarPadraoSucesso(strLabel);
          if (data.error) {
            consultarPadraoExcessao();
            dialogAlert(strAtencao, data.error.msg, 6);
            return;
          }

          consultarPadraoSucessoPaginacao(data);
      }).fail(function (data) {
          consultarPadraoFalha(strLabel);
          dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function enterPesquisarAprovacoesCompras(e) {
  if (e.keyCode == 13) {
    consultarAprovacoesCompras();
  }
}

function checarBtnAprovar() {
  $("#btnComprasAprovar").hide();

  $(".marcar").each(function () {
    if (this.checked) {
      $("#btnComprasAprovar").show();
      return;
    }
  });
}

function confirmarCheckboxes(
  strRota,
  strTitulo,
  strMensagem,
  strTipo,
  strCallback
) {
  var types = BootstrapDialog.TYPE_DEFAULT;
  var btnCSS = "btn-default";

  if (strTipo == strBootstrapCodigoInfo) {
    //2
    types = BootstrapDialog.TYPE_INFO;
    btnCSS = "btn-info";
  } else if (strTipo == strBootstrapCodigoPrimary) {
    //3
    types = BootstrapDialog.TYPE_PRIMARY;
    btnCSS = "btn-primary";
  } else if (strTipo == strBootstrapCodigoSuccess) {
    //4
    types = BootstrapDialog.TYPE_SUCCESS;
    btnCSS = "btn-success";
  } else if (strTipo == strBootstrapCodigoWarning) {
    //5
    types = BootstrapDialog.TYPE_WARNING;
    btnCSS = "btn-warning";
  } else if (strTipo == strBootstrapCodigoDanger) {
    //6
    types = BootstrapDialog.TYPE_DANGER;
    btnCSS = "btn-danger";
  }

  var arrSelecionados = new Array();
  $("input[type=checkbox][name='chkSelecionar[]']:checked").each(function () {
    arrSelecionados.push($(this).val());
  });

  if (arrSelecionados.length == 0) {
    $.notify("No minímo 1 (UMA) opção deve ser selecionada.", "warn");
    return;
  } else {
    BootstrapDialog.show({
      title: strTitulo,
      message: strMensagem,
      size: BootstrapDialog.SIZE_WIDE,
      type: types,
      id: "dialogExcluirModal",
      buttons: [
        {
          label: strLabelNao,
          action: function (dialogItself) {
            dialogItself.close();
          },
        },
        {
          label: strLabelSim,
          cssClass: btnCSS,
          data: {
            js: "btn-confirm",
            "user-id": "3",
          },
          id: "btnConfirmPadraoYes",
          action: function () {
            $("#btnConfirmPadraoYes").prop("disabled", true);
            $("#btnConfirmPadraoYes").html(strCarregando);

            $.ajax({
              url: $.trim(strRota),
              dataType: "json",
              cache: false,
              data: {
                valor: "1",
                arrValores: arrSelecionados,
              },
              type: "POST",
            })
              .success(function (data) {
                if (data.error) {
                  $("#btnConfirmPadraoYes").prop("disabled", false);
                  $("#btnConfirmPadraoYes").html(strLabelSim);

                  dialogAlert(strAtencao, data.error.msg, 6);
                  return;
                }

                $.notify(data.mensagem, "success");

                if ($.trim(strCallback) != "") {
                  eval(strCallback);
                }

                $("#btnConfirmPadraoYes").prop("disabled", false);
                $("#btnConfirmPadraoYes").html(strLabelSim);
                $(".modal").modal("hide");
              })
              .fail(function (data) {
                dialogAlert(strAtencao, data.responseText, 6);

                $("#btnConfirmPadraoYes").prop("disabled", false);
                $("#btnConfirmPadraoYes").html(strLabelSim);
                $(".modal").modal("hide");
              });
          },
        },
      ],
    });

    setTimeout(function () {
      $(".bootstrap-dialog-message").html(
        $(".bootstrap-dialog-message").html() +
        "<br>Total selecionados(s): " +
        arrSelecionados.length
      );
    }, 500);
  }
}

function enterPesquisarTerrenosWorkFlow(e) {
  if (e.keyCode == 13) {
    consultarTerrenosWorkFlow();
  }
}

function consultarTerrenosWorkFlow() {
  var strLabel = consultarPadraoInicial();
  var arrGestores = new Array();
  var arrZonas = new Array();
  var arrStatus = new Array();
  var arrCorretores = new Array();
  var arrEstados = new Array();
  var arrCidades = new Array();
  var strComite = "";

  if ($("#TER_FlagComite").is(":checked")) {
    strComite = strSim;
  }

  $("select[name='CAX_Gestor_ID[]'] option:selected").each(function () {
    arrGestores.push($(this).val());
  });

  $("select[name='CAX_Zona_ID[]'] option:selected").each(function () {
    arrZonas.push($(this).val());
  });

  $("select[name='CAX_Status_ID[]'] option:selected").each(function () {
    arrStatus.push($(this).val());
  });

  $("select[name='COR_ID[]'] option:selected").each(function () {
    arrCorretores.push($(this).val());
  });

  $("select[name='UF_ID[]'] option:selected").each(function () {
    arrEstados.push($(this).val());
  });

  $("select[name='TER_Cidade[]'] option:selected").each(function () {
    arrCidades.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#compro_terreno_workflow_consultar").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      TER_Pesquisar: $.trim($("#TER_Pesquisar").val()),
      CAX_Gestor_ID: arrGestores,
      CAX_Zona_ID: arrZonas,
      CAX_Status_ID: arrStatus,
      COR_ID: arrCorretores,
      UF_ID: arrEstados,
      TER_Cidade: arrCidades,
      TER_FlagComite: strComite,
      DATAINICIAL: $.trim($("#txtDataInicial").val()),
      DATAFINAL: $.trim($("#txtDataFinal").val()),
    },
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);
      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function impostosItemPedidos(itemPedidoID, insumoDescricao) {
  preLoadingOpen();

  $.post(
    $.trim($("#itens_pedidos_impostos_novo").val()),
    {
      PED_ID: $.trim($("#PED_ID").val()),
      PDI_ID: $.trim(itemPedidoID),
      INS_Descricao: $.trim(insumoDescricao),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function salvarImpostosItemPedido() {
  $("button").prop("disabled", true);

  $.ajax({
    url: $.trim($("#itens_pedidos_impostos_salvar").val()),
    dataType: "json",
    cache: false,
    data: {
      PED_ID: $.trim($("#hddPedido").val()),
      PDI_ID: $.trim($("#hddItemPedido").val()),
      PDI_Percentual_IPI: $.trim($("#PDI_Percentual_IPI").val()),
      PDI_Percentual_IIS: $.trim($("#PDI_Percentual_IIS").val()),
      PDI_Percentual_ICMS: $.trim($("#PDI_Percentual_ICMS").val()),
    },
    type: "POST",
  }).success(function (data) {
    //alert(data); return;
    if (data.error) {
      $.notify(data.error.msg, "error");
      $("button").prop("disabled", false);
      return;
    }

    consultarItensPedidos($.trim($("#hddPedido").val()));
    $.notify(data.mensagem, "success");

    $(".modal").modal("hide");
    $("button").prop("disabled", false);
  });
}

function confirmarFormaPagamentoParcelaContasPagar(
  pagarID,
  parcelaID,
  strMensagem
) {
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#contas_pagar_formas_pagamentos").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_ID: $.trim(pagarID),
      CPP_ID: $.trim(parcelaID),
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        preLoadingClose();
        $.notify(data.error.msg, "error");
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3);
      setTimeout(function () {
        $("#BAN_ID, #CPP_FormaPagamento, #CPP_TipoChavePIX").chosen();
        $("#BAN_ID, #CPP_FormaPagamento, #CPP_TipoChavePIX").trigger("chosen:updated");

        //Clique salvar
        $("#btnSalvarFormaPagamento").click(function (e) {
          if (
            $("#BAN_ID").val() !== undefined &&
            $.trim($("#BAN_ID").val()) == ""
          ) {
            $.notify("Banco precisa ser informada.", "warn");
            return;
          } else if ($.trim($("#CPP_FormaPagamento").val()) == "") {
            $.notify("Forma de pagamento precisa ser informada.", "warn");
            return;
          }

          if (
            $("#CPP_Agencia").val() !== undefined &&
            $.trim($("#CPP_Agencia").val()) == ""
          ) {
            $.notify("Agência precisa ser informada.", "warn");
            return;
          } else if (
            $("#CPP_ContaCorrente").val() !== undefined &&
            $.trim($("#CPP_ContaCorrente").val()) == ""
          ) {
            $.notify("Conta precisa ser informada.", "warn");
            return;
          } else if (
            $("#CPP_LinhaDigitavel").val() !== undefined &&
            $("#CPP_CodigodeBarras").val() !== undefined
          ) {
            if (
              $.trim($("#CPP_LinhaDigitavel").val()) == "" &&
              $.trim($("#CPP_CodigodeBarras").val()) == ""
            ) {
              $.notify(
                "Linha digitável ou código de barras deve ser informado.",
                "warn"
              );
              return;
            }
          }

          if (
            $.trim($("#CPP_FormaPagamento").val()) ==
            $.trim($("#hddFlagFinanceiroFormaPagamentoCNPJRET").val())
          ) {
            //CNPJ RET
            if (
              $("#CPP_CNPJRet").val() !== undefined &&
              $.trim($("#CPP_CNPJRet").val()) == ""
            ) {
              $.notify("CNPJ precisa ser informada.", "warn");
              return;
            }
          }

          if (
            $.trim($("#CPP_FormaPagamento").val()) ==
            $.trim($("#hddFlagFinanceiroFormaPagamentoDARF").val())
          ) {
            //DARF
            if (
              $("#CPP_CodigodaReceita").val() !== undefined &&
              $.trim($("#CPP_CodigodaReceita").val()) == ""
            ) {
              $.notify("Código da receita precisa ser informada.", "warn");
              return;
            } else if (
              $("#CPP_PeriododeApuracao").val() !== undefined &&
              $.trim($("#CPP_PeriododeApuracao").val()) == ""
            ) {
              $.notify("Período de apuração precisa ser informado.", "warn");
              return;
            }
          } else if (
            $.trim($("#CPP_FormaPagamento").val()) ==
            $.trim($("#hddFlagFinanceiroFormaPagamentoDARFSem").val())
          ) {
            //DARF	Sem código de barras
            if (
              $("#CPP_CodigodaReceita").val() !== undefined &&
              $.trim($("#CPP_CodigodaReceita").val()) == ""
            ) {
              $.notify("Código da receita precisa ser informada.", "warn");
              return;
            } else if (
              $("#CPP_PeriododeApuracao").val() !== undefined &&
              $.trim($("#CPP_PeriododeApuracao").val()) == ""
            ) {
              $.notify("Período de apuração precisa ser informado.", "warn");
              return;
            }
          } else if (
            $.trim($("#CPP_FormaPagamento").val()) ==
            $.trim($("#hddFlagFinanceiroFormaPagamentoDARF").val())
          ) {
            //FGTS
            if (
              $("#CPP_CodigodaReceita").val() !== undefined &&
              $.trim($("#CPP_CodigodaReceita").val()) == ""
            ) {
              $.notify("Código da receita precisa ser informada.", "warn");
              return;
            } else if (
              $("#CPP_PeriododeApuracao").val() !== undefined &&
              $.trim($("#CPP_PeriododeApuracao").val()) == ""
            ) {
              $.notify("Período de apuração precisa ser informado.", "warn");
              return;
            } else if (
              $("#CPP_Referencia").val() !== undefined &&
              $.trim($("#CPP_Referencia").val()) == ""
            ) {
              $.notify("Referência precisa ser informada.", "warn");
              return;
            }
          }

          //GPS
          if (
            $("#CPP_Identificador").val() !== undefined &&
            $.trim($("#CPP_Identificador").val()) == ""
          ) {
            $.notify("Identificador precisa ser informado.", "warn");
            return;
          } else if (
            $("#CPP_CodigoPagamento").val() !== undefined &&
            $.trim($("#CPP_CodigoPagamento").val()) == ""
          ) {
            $.notify("Código pagamento precisa ser informado.", "warn");
            return;
          } else if (
            $("#CPP_CompetenciaGPS").val() !== undefined &&
            $.trim($("#CPP_CompetenciaGPS").val()) == ""
          ) {
            $.notify("Competência GPS precisa ser informado.", "warn");
            return;
          } else if (
            $("#CPP_ValorOutrasEntidades").val() !== undefined &&
            $.trim($("#CPP_ValorOutrasEntidades").val()) == ""
          ) {
            $.notify("Valor outras entidades precisa ser informado.", "warn");
            return;
          }

          //PIX          
          if (
            $.trim($("#CPP_FormaPagamento").val()) ==
            $.trim($("#hddFlagFinanceiroFormaPagamentoPIX").val())
          ) {
            //PIX
            if (
              $("#CPP_TipoChavePIX").val() !== undefined &&
              $.trim($("#CPP_TipoChavePIX").val()) == ""
            ) {
              $.notify("Tipo da chave do PIX precisa ser informada.", "warn");
              return;
            }

            if (
              $("#CPP_PIX").val() !== undefined &&
              $.trim($("#CPP_PIX").val()) == ""
            ) {
              $.notify("Chave do PIX precisa ser informada.", "warn");
              return;
            }
          }

          $("#btnSalvarFormaPagamento").prop("disabled", true);
          var strLabel = $("#btnSalvarFormaPagamento").html();
          $("#btnSalvarFormaPagamento").html(strCarregando);

          var arrDados = new FormData();
          arrDados.append("CPG_ID", $.trim(pagarID));
          arrDados.append("CPP_ID", $.trim(parcelaID));
          arrDados.append("BAN_ID", $.trim($("#BAN_ID").val()));
          arrDados.append(
            "CPP_FormaPagamento",
            $.trim($("#CPP_FormaPagamento").val())
          );
          arrDados.append(
            "CPP_FormaPagamentoTexto",
            $.trim($("#CPP_FormaPagamento option:selected").text())
          );

          if (
            $("#BAN_ID").val() !== undefined &&
            $.trim($("#BAN_ID").val()) != ""
          ) {
            arrDados.append(
              "BAN_Banco",
              $.trim($("#BAN_ID option:selected").text())
            );
          }

          if ($("#CPP_TipoContaBancaria").val() !== undefined) {
            arrDados.append(
              "CPP_TipoContaBancaria",
              $.trim($("#CPP_TipoContaBancaria").val())
            );
            arrDados.append(
              "CPP_TipoContaBancariaTexto",
              $.trim($("#CPP_TipoContaBancaria option:selected").text())
            );
          }

          if ($("#CPP_Agencia").val() !== undefined) {
            arrDados.append("CPP_Agencia", $.trim($("#CPP_Agencia").val()));
          }

          if ($("#CPP_DvAgencia").val() !== undefined) {
            arrDados.append("CPP_DvAgencia", $.trim($("#CPP_DvAgencia").val()));
          }

          if ($("#CPP_ContaCorrente").val() !== undefined) {
            arrDados.append(
              "CPP_ContaCorrente",
              $.trim($("#CPP_ContaCorrente").val())
            );
          }

          if ($("#CPP_DvContaCorrente").val() !== undefined) {
            arrDados.append(
              "CPP_DvContaCorrente",
              $.trim($("#CPP_DvContaCorrente").val())
            );
          }

          if ($("#CPP_LinhaDigitavel").val() !== undefined) {
            arrDados.append(
              "CPP_LinhaDigitavel",
              $.trim($("#CPP_LinhaDigitavel").val())
            );
          }

          if ($("#CPP_CodigodeBarras").val() !== undefined) {
            arrDados.append(
              "CPP_CodigodeBarras",
              $.trim($("#CPP_CodigodeBarras").val())
            );
          }

          if ($("#CPP_PIX").val() !== undefined) {
            arrDados.append("CPP_PIX", $.trim($("#CPP_PIX").val()));
          }

          if ($("#CPP_TipoChavePIX").val() !== undefined) {
            arrDados.append("CPP_TipoChavePIX", $.trim($("#CPP_TipoChavePIX").val()));
          }

          if ($("#CPP_CodigodaReceita").val() !== undefined) {
            arrDados.append(
              "CPP_CodigodaReceita",
              $.trim($("#CPP_CodigodaReceita").val())
            );
          }

          if ($("#CPP_PeriododeApuracao").val() !== undefined) {
            arrDados.append(
              "CPP_PeriododeApuracao",
              $.trim($("#CPP_PeriododeApuracao").val())
            );
          }

          if ($("#CPP_Referencia").val() !== undefined) {
            arrDados.append(
              "CPP_Referencia",
              $.trim($("#CPP_Referencia").val())
            );
          }

          if ($("#CPP_Identificador").val() !== undefined) {
            arrDados.append(
              "CPP_Identificador",
              $.trim($("#CPP_Identificador").val())
            );
          }

          if ($("#CPP_CodigoPagamento").val() !== undefined) {
            arrDados.append(
              "CPP_CodigoPagamento",
              $.trim($("#CPP_CodigoPagamento").val())
            );
          }

          if ($("#CPP_CompetenciaGPS").val() !== undefined) {
            arrDados.append(
              "CPP_CompetenciaGPS",
              $.trim($("#CPP_CompetenciaGPS").val())
            );
          }

          if ($("#CPP_ValorOutrasEntidades").val() !== undefined) {
            arrDados.append(
              "CPP_ValorOutrasEntidades",
              $.trim($("#CPP_ValorOutrasEntidades").val())
            );
          }

          if ($("#CPP_CNPJRet").val() !== undefined) {
            arrDados.append("CPP_CNPJRet", $.trim($("#CPP_CNPJRet").val()));
          }

          $.ajax({
            url: $.trim($("#contas_pagar_formas_pagamentos_salvar").val()),
            dataType: "json",
            cache: false,
            contentType: false,
            processData: false,
            data: arrDados,
            type: "POST",
          })
            .success(function (data) {
              $("#btnSalvarFormaPagamento").html(strLabel);
              $("#btnSalvarFormaPagamento").prop("disabled", false);

              if (data.error) {
                dialogAlert(strAtencao, data.error.msg, 6);
                return;
              }

              $(".modal").modal("hide");
              $.notify(data.mensagem, "success");

              if ($("#txtDataInicial").val() != undefined) {
                $("#btnFiltrar").click();
              }
            })
            .fail(function (data) {
              $("#btnSalvarFormaPagamento").html(strLabel);
              $("#btnSalvarFormaPagamento").prop("disabled", false);

              dialogAlert(strAtencao, data.responseText, 6);
            });
        });

        $("#CPP_FormaPagamento").change(function (e) {
          $("#divCampos").html("");

          if ($.trim(this.value) != "") {
            $("#btnSalvarFormaPagamento").prop("disabled", true);
            $("#divCampos").html(strCarregando);

            $.ajax({
              url: $.trim($("#contas_pagar_formas_pagamentos_campos").val()),
              dataType: "json",
              cache: false,
              data: {
                CPG_ID: $.trim(pagarID),
                CPP_ID: $.trim(parcelaID),
                CPP_FormaPagamento: $.trim(this.value),
              },
              type: "POST",
            })
              .success(function (data2) {
                $("#btnSalvarFormaPagamento").prop("disabled", false);

                if (data2.error) {
                  $("#divCampos").html("");
                  dialogAlert(strAtencao, data2.error.msg, 6);
                  return;
                }

                $("#divCampos").html(data2.strHtml);
                $('#CPP_TipoChavePIX').chosen();
                setInitFunctions();
              })
              .fail(function (data2) {
                $("#btnSalvarFormaPagamento").prop("disabled", false);
                $("#divCampos").html("");

                dialogAlert(strAtencao, data2.responseText, 6);
              });
          }
        });

        if ($.trim($("#CPP_FormaPagamento").val()) != "") {
          $("#CPP_FormaPagamento").trigger("change");
        }
      }, 500);

      preLoadingClose();
    })
    .fail(function (data) {
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function confirmarFinanceiroAprovacoes(strMensagem) {
  var arrContas = new Array();
  var strHtml = "<br>";

  $("input[type='checkbox'][name='chkSelecionar[]']:checked").each(function () {
    arrContas.push($(this).val());
    strHtml += $.trim($(this).attr("list"));
  });

  strMensagem += strHtml;

  if (arrContas.length > 0) {
    BootstrapDialog.show({
      title: strInformacao,
      size: BootstrapDialog.SIZE_WIDE,
      message: strMensagem,
      type: BootstrapDialog.TYPE_SUCCESS,
      buttons: [
        {
          label: strLabelNao,
          cssClass: "btn-danger btn-dialog-confirm",
          action: function (dialogItself) {
            dialogItself.close();
          },
        },
        {
          label: strLabelSim,
          cssClass: "btn-success btn-dialog-confirm",
          id: "btnConfirmarDialog",
          data: {
            js: "btn-confirm",
            "user-id": "3",
          },
          action: function () {
            $(".btn-dialog-confirm").prop("disabled", true);
            var strLabel = $("#btnConfirmarDialog").html();
            $("#btnConfirmarDialog").html(strCarregando);

            $.ajax({
              url: $.trim($("#hddFinanceiroContasAprovar").val()),
              dataType: "json",
              cache: false,
              data: {
                CPG_ID: arrContas,
              },
              type: "POST",
            })
              .success(function (data) {
                $(".btn-dialog-confirm").prop("disabled", false);
                $("#btnConfirmarDialog").html(strLabel);

                if (data.error) {
                  dialogAlert(strAtencao, data.error.msg, 6);
                  return;
                }

                fecharModal();
                consultarContasPagar();
                $.notify(data.mensagem, "success");
              })
              .fail(function (data) {
                $(".btn-dialog-confirm").prop("disabled", false);
                $("#btnConfirmarDialog").html(strLabel);

                dialogAlert(strAtencao, data.responseText, 6);
              });
          },
        },
      ],
    });
  } else {
    $.notify("Selecione no minímo 1 (UMA) opção para aprovar.", "warn");
    return;
  }
}

function enterPesquisarAcoes(e) {
  if (e.keyCode == 13) {
    consultarAcoes();
  }
}

function consultarAcoes() {
  /*  	preLoadingOpen();
  $('button').prop('disabled', true);
  $('#consultar-dados').html(strCarregando); */

  $.ajax({
    url: $.trim($("#acoes_consultar2").val()),
    dataType: "json",
    cache: false,
    data: {
      ACO_Descricao: $.trim($("#ACO_Descricao").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      $("button").prop("disabled", false);
      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      //$('#consultar-dados').html(data.strHtml);
      $("#pagination").html(data.pagination);

      for (i in data.arrDados) {
        var strHtml = "<tr>";
        strHtml += "<td>" + data.arrDados[i].ACO_ID + "</td>";
        strHtml += "<td>" + data.arrDados[i].USU_ID + "</td>";
        strHtml += "<td>" + data.arrDados[i].ACO_Descricao + "</td>";
        strHtml +=
          "<td>" + data.arrDados[i].ACO_DataHoraUltimaAtualizacao + "</td>";
        strHtml += "<td>" + data.arrDados[i].ACO_Status + "</td>";
        strHtml += "</tr>";

        $("#cntConsulta tbody").append(strHtml);
      }

      //createPagination(0);

      $("#pagination").on("click", "a", function (e) {
        e.preventDefault();
        var pageNum = $(this).attr("data-ci-pagination-page");
        createPagination(pageNum);
      });

      return;
    })
    .fail(function (data) {
      //alert(jqXHR); return;
      preLoadingClose();
      $("button").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function createPagination(pageNum, url) {
  $.ajax({
    url: url,
    type: "get",
    dataType: "json",
    success: function (responseData) {
      $("#pagination").html(responseData.pagination);
      paginationData(responseData.empData);

      $("#cntConsulta").DataTable({
        destroy: true,
        bPaginate: true,
        responsive: true,
        paging: false,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: false,
        autoWidth: true,
        dom: "lBfrtip",
        order: [],
        lengthMenu: [10, 20, 50, 100, 200, 500],
        iDisplayLength: 10,
        language: {
          url: $.trim($("#hddFile").val()),
        },
      });
    },
  });
}

/*function consultarFinanceiroContasPagarParcelas(){
  if ($.trim($('#EMP_ID').val()) == ''){
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  }else if ($.trim($('#CON_ID').val()) == ''){
    $.notify("Conta Bancária precisa ser informada.", "warn");
    return;
  }else{
      preLoadingOpen();
    $('#consultar-dados').html(strCarregando);

    $.post($.trim($('#financeiro_lotes_pesquisar').val()), {
      EMP_ID: $.trim($('#EMP_ID').val()),
      CON_ID: $.trim($('#CON_ID').val()),
      CPP_DataVencimentoInicial: $.trim($('#txtDataInicial').val()),
      CPP_DataVencimentoFinal: $.trim($('#txtDataFinal').val())
    },
    function(data){
      //alert(data); return;
      if (data.sucesso == 'true'){
        $('#consultar-dados').html(data.strHtml);
        $.notify(data.mensagem, "success");
      }else{
        $('#consultar-dados').html('');
        $.notify(data.mensagem, "error");
      }

      preLoadingClose();
      return;
      }, 'json'
    );
  }
}*/

function confirmarParcelasLotes(strMensagem) {
  var arrParcelas = new Array();

  $("input[type='checkbox'][name='parcelas[]']:checked").each(function () {
    arrParcelas.push($(this).val());
  });

  if (arrParcelas.length > 0) {
    BootstrapDialog.show({
      title: strInformacao,
      message: strMensagem,
      size: BootstrapDialog.SIZE_WIDE,
      type: BootstrapDialog.TYPE_SUCCESS,
      buttons: [
        {
          label: strLabelNao,
          action: function (dialogItself) {
            dialogItself.close();
          },
        },
        {
          label: strLabelSim,
          cssClass: "btn-success",
          id: "btnConfirmarDialog",
          data: {
            js: "btn-confirm",
            "user-id": "3",
          },
          action: function () {
            $("button").prop("disabled", true);

            $.ajax({
              url: $.trim($("#financeiro_lotes_salvar").val()),
              dataType: "json",
              cache: false,
              data: {
                CON_ID: $.trim($("#CON_ID").val()),
                EMP_ID: $.trim($("#EMP_ID").val()),
                CTP_ID: arrParcelas,
              },
              type: "POST",
            })
              .success(function (data) {
                //alert(data); return;
                $("button").prop("disabled", false);

                if (data.error) {
                  dialogAlert(strInformacao, data.error.msg, 6);
                  return;
                }

                $(".modal").modal("hide");
                $.notify(data.mensagem, "success");
                consultarFinanceiroContasPagarParcelas();

                return;
              })
              .fail(function (data) {
                //alert(data); return;
                $("button").prop("disabled", false);
                dialogAlert(strAtencao, data.responseText, 6);
                return;
              });
          },
        },
      ],
    });
  } else {
    $.notify("Selecione no minímo 1 (UMA) opção para gerar lote.", "warn");
    return;
  }
}

function consultarFinanceiroLotes() {
  var strLabel = consultarPadraoInicial();
  var arrEmpresas = new Array();
  var arrContas = new Array();
  var arrAprovados = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='CON_ID[]'] option:selected").each(function () {
    arrContas.push($(this).val());
  });

  $("select[name='SEL_SimNao[]'] option:selected").each(function () {
    arrAprovados.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#financeiro_lotes_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: arrEmpresas,
      CON_ID: arrContas,
      SGP_Aprovados: arrAprovados,
      LOT_NumeroLote: $.trim($("#LOT_NumeroLote").val()),
      CPG_Numero: $.trim($("#CPG_Numero2").val()),
      LOT_DataHoraCadastroInicial: $.trim($("#txtDataInicial").val()),
      LOT_DataHoraCadastroFinal: $.trim($("#txtDataFinal").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);
      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarFinanceiroLotes(e) {
  $("#txtDataInicial, #txtDataFinal").val("");
  if (e.keyCode == 13) {
    consultarFinanceiroLotes();
  }
}

function carregarContasBancariasEmpresasMultiplos(arrEmpresas, chosen = true) {
  preLoadingOpen();
  $("#btnFiltrar").prop("disabled", true);

  if (chosen) {
    $("#CON_ID").val("");
    $("#CON_ID").html("<option value='" + strSelecione + "'></option>");
    $("#CON_ID").trigger("chosen:updated");
  } else {
    $("#CON_ID").multiselect("destroy");
    $("#CON_ID").html("");
  }

  $.ajax({
    url: $.trim($("#contas_bancarias_empresas").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      EMP_ID: arrEmpresas,
    },
  })
    .success(function (data) {
      preLoadingClose();
      $("#btnFiltrar").prop("disabled", false);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      var strHtml = "";
      var executar = false;
      if (data.arrDados.length > 0) {
        for (var i = 0; i < data.arrDados.length; i++) {
          var strDescricao = data.arrDados[i].CON_Descricao;

          if (data.arrDados[i].EMP_NomeFantasia !== undefined) {
            strDescricao += " (" + data.arrDados[i].EMP_NomeFantasia + ")";
          }

          strHtml += "<option ";

          if (data.arrDados.length == 1) {
            executar = true;
            strHtml += " selected ";
          }

          strHtml +=
            " value='" +
            data.arrDados[i].CON_ID +
            "'>" +
            strDescricao +
            "</option>";
        }
      }

      $("#CON_ID").append(strHtml);
      if (chosen) {
        $("#CON_ID").trigger("chosen:updated");
      } else {
        $("#CON_ID").multiselect("refresh");
      }

      if (executar) {
        $("#CON_ID").trigger("change");
      }
    })
    .fail(function (data) {
      preLoadingClose();
      $("#btnFiltrar").prop("disabled", false);

      if (chosen) {
        $("#CON_ID").trigger("chosen:updated");
      } else {
        $("#CON_ID").multiselect("refresh");
      }

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function carregarVagasEmpreendimentos(estruturaID, unidadeID, propostaID) {
  $("#boxVagas").show();
  $("#divVagas").html(strCarregando);

  $.post(
    $.trim($("#hddPropostasVagasEmpreendimentos").val()),
    {
      GRE_ID: $.trim($("#GRE_ID").val()),
      EST_ID: estruturaID,
      UNI_ID: unidadeID,
      PRO_ID: propostaID,
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#divVagas").html(data.strHtml);

        setTimeout(function () {
          setInitFunctions();
        }, 1000);
      } else {
        $("#divVagas").html("");
        $("#boxVagas").hide();
      }
    },
    "json"
  );
}

function consultarPropostasVagas() {
  preLoadingOpen();
  $("#tab_vagas").html(strCarregando);

  $.post(
    $.trim($("#carteiras_contratos_propostas_vagas").val()),
    {
      CTO_ID: $.trim($("#CTO_ID").val()),
    },
    function (data) {
      // alert(data); return;
      $("#tab_vagas").html(data.strHtml);
      preLoadingClose();
    },
    "json"
  );
}

function formularioNovaVaga(intVaga) {
  preLoadingOpen();

  $.post(
    $.trim($("#carteiras_contratos_novas_vagas").val()),
    {
      CTV_ID: intVaga,
      CTO_ID: $.trim($("#hddContrato").val()),
      EST_ID: $.trim($("#EST_ID").val()),
      UNI_ID: $.trim($("#UNI_ID").val()),
    },
    function (data) {
      // alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "error");
      }

      preLoadingClose();
    },
    "json"
  );
}

function salvarNovasVagas() {
  if ($.trim($("#UNI_ID2").val()) == "") {
    $.notify("Unidade precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CTV_ValorVaga").val()) == "") {
    $.notify("Valor da vaga precisa ser informada.", "warn");
    return;
  } else {
    $("input, .btn-formulario").prop("disabled", true);

    $.post(
      $.trim($("#carteiras_contratos_salvar_vagas").val()),
      {
        CTV_ID: $.trim($("#CTV_ID").val()),
        CTO_ID: $.trim($("#hddContrato").val()),
        UNI_ID: $.trim($("#UNI_ID2").val()),
        CTV_ValorVaga: $.trim($("#CTV_ValorVaga").val()),
      },
      function (data) {
        //alert(data); return;
        $("input, .btn-formulario").prop("disabled", false);

        if (data.sucesso == "true") {
          consultarPropostasVagas();
          $(".modal").modal("hide");
          $.notify(data.mensagem, "success");
        } else {
          $.notify(data.mensagem, "error");
        }

        return;
      },
      "json"
    );
  }
}

function consultarFinanceiroContasPagarLog() {
  preLoadingOpen();
  $("#tab_log").html(strCarregando);

  $.post(
    $.trim($("#contas_pagar_log_consultar").val()),
    {
      CPG_ID: $.trim($("#CPG_ID").val()),
    },
    function (data) {
      //alert(data); return;
      $("#tab_log").html(data.strHtml);
      preLoadingClose();
    },
    "json"
  );
}

function propostaSelecionarMinuta(type = null) {
  preLoadingOpen();

  $.post(
    $.trim($("#propostas_minutas_consultar").val()),
    {
      PRO_ID: $.trim($("#PRO_ID").val()),
      type: type,      
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function propostaSelecionarMinutaVendas() {
  preLoadingOpen();

  $.post(
    $.trim($("#portal_vendas_minutas_consultar").val()),
    {
      GRE_ID: $.trim($("#GRE_ID").val()),
      PRO_ID: $.trim($("#PRO_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function propostaSelecionarEmTempo() {
  preLoadingOpen();

  $.post(
    $.trim($("#propostas_em_tempo_adicionar").val()),
    {
      PRO_ID: $.trim($("#PRO_ID").val()),
    },
    function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        dialogAlert2(data.strTitulo, data.strHtml, 3);

        setTimeout(function () {
          $(".textarea").wysihtml5();

          $("#btnAtualizarEmTempo").click(function (e) {
            $("button").prop("disabled", true);

            $.ajax({
              url: $.trim($("#propostas_em_tempo_salvar").val()),
              dataType: "json",
              cache: false,
              data: {
                PRO_ID: $.trim($("#PRO_ID").val()),
                PRO_EmTempo: $("iframe")
                  .contents()
                  .find(".wysihtml5-editor")
                  .html(),
              },
              type: "POST",
            })
              .done(function (data) {
                //alert(data); return;
                $("button").prop("disabled", false);
                $(".modal").modal("hide");

                if (data.error) {
                  dialogAlert(strInformacao, data.error.msg, 6);
                  return;
                }

                $.notify(data.mensagem, "success");
                return;
              })
              .fail(function (data) {
                //alert(data); return;
                $("button").prop("disabled", false);
                $(".modal").modal("hide");

                dialogAlert(strAtencao, data.responseText, 6);
                return;
              });
          });

          preLoadingClose();
        }, 500);
      } else {
        $.notify(data.mensagem, "error");
      }
      preLoadingClose();
      return;
    },
    "json"
  );
}

function editarEntidadeRapido(entidadeID) {
  $("#hddCodigoSelecionado").val(entidadeID);
  $("#btnFormularioNovoCliente").trigger("click");
}

function consultarCarteirasRemessas() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#EST_ID").val()) == "") {
    $.notify("Empreendimento precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CON_ID").val()) == "") {
    $.notify("Conta Bancária precisa ser informada.", "warn");
    return;
  } else {
    preLoadingOpen();
    $("#consultar-dados").html(strCarregando);

    $.post(
      $.trim($("#carteiras_remessas_consultar").val()),
      {
        EMP_ID: $.trim($("#EMP_ID").val()),
        EST_ID: $.trim($("#EST_ID").val()),
        CON_ID: $.trim($("#CON_ID").val()),
        CTP_DataVencimentoInicial: $.trim($("#txtDataInicial").val()),
        CTP_DataVencimentoFinal: $.trim($("#txtDataFinal").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#consultar-dados").html(data.strHtml);
        } else {
          $("#consultar-dados").html("");
          $.notify(data.mensagem, "error");
        }
        preLoadingClose();
      },
      "json"
    );
  }
}

function consultarCarteirasRemessasDados() {
  var strLabel = consultarPadraoInicial();

  $.post(
    $.trim($("#carteiras_remessas_consultar_dados").val()),
    {
      EMP_ID: $.trim($("#EMP_ID").val()),
      CON_ID: $.trim($("#CON_ID").val()),
      REM_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      REM_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
    },
    function (data) {
      if (data.sucesso == "true") {
        consultarPadraoSucesso(strLabel);
        consultarPadraoSucessoPaginacao(data);
      } else {
        consultarPadraoExcessao();
        consultarPadraoFalha(strLabel);

        $.notify(data.mensagem, "warn");
      }
    },
    "json"
  );
}

function consultarCarteirasRemessasBoletos() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#EST_ID").val()) == "") {
    $.notify("Empreendimento precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CON_ID").val()) == "") {
    $.notify("Conta Bancária precisa ser informada.", "warn");
    return;
  } else {
    preLoadingOpen();
    $("#consultar-dados").html(strCarregando);

    $.post(
      $.trim($("#carteiras_remessas_adicionar").val()),
      {
        EMP_ID: $.trim($("#EMP_ID").val()),
        EST_ID: $.trim($("#EST_ID").val()),
        CON_ID: $.trim($("#CON_ID").val()),
        CTP_DataVencimentoInicial: $.trim($("#txtDataInicial").val()),
        CTP_DataVencimentoFinal: $.trim($("#txtDataFinal").val()),
      },
      function (data) {
        //alert(data); return;
        if (data.sucesso == "true") {
          $("#consultar-dados").html(data.strHtml);
        } else {
          $("#consultar-dados").html("");
          $.notify(data.mensagem, "error");
        }
        preLoadingClose();
      },
      "json"
    );
  }
}

function salvarCarteirasArquivoRetorno() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CON_ID").val()) == "") {
    $.notify("Conta Bancária precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#RET_Arquivo").val()) == "") {
    $.notify("Arquivo de retorno precisa ser informado.", "warn");
    return;
  } else {
    preLoadingOpen();
    var strLabel = $("#btnFiltrar").html();
    $("#btnFiltrar").prop("disabled", true);
    $("#btnFiltrar").html(strCarregando);

    var arrDados = new FormData();

    arrDados.append("EMP_ID", $.trim($("#EMP_ID").val()));
    arrDados.append("CON_ID", $.trim($("#CON_ID").val()));
    arrDados.append("RET_Arquivo", $("#RET_Arquivo").prop("files")[0]);

    $.ajax({
      url: $.trim($("#carteiras_arquivo_retorno_salvar").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "POST",
    })
      .success(function (data){
        $("#btnFiltrar").prop("disabled", false);
        $("#btnFiltrar").html(strLabel);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        // $('#EMP_ID, #CON_ID, #RET_Arquivo').val('');
        // $('#EMP_ID, #CON_ID').trigger("chosen:updated");

        dialogAlert(strInformacao, data.strHtml, 4);
      })
      .fail(function (data) {
        $("#btnFiltrar").prop("disabled", false);
        $("#btnFiltrar").html(strLabel);
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function enterPesquisarCarteirasArquivoRetorno(e) {
  if (e.keyCode == 13) {
    consultarCarteirasArquivoRetorno();
  }
}

function consultarCarteirasArquivoRetorno() {
  var strLabel = consultarPadraoInicial();

  $.ajax({
    url: $.trim($("#carteiras_arquivo_retorno_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: $.trim($("#EMP_ID").val()),
      CON_ID: $.trim($("#CON_ID").val()),
      RET_Protocolo: $.trim($("#RET_Protocolo").val()),
      RET_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      RET_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);
      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function formularioOrdemCadastrosAuxiliares(intTipoCadastroAuxiliares) {
  preLoadingOpen();

  $.ajax({
    url:
      $.trim($("#cadastros_auxiliares_adicionar_ordem").val()) +
      "/" +
      $.trim(intTipoCadastroAuxiliares),
    dataType: "json",
    cache: false,
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      $("button").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      dialogAlert(data.strTitulo, data.strHtml, 3);

      setTimeout(function () {
        $("#btnSalvarCadastrosAuxiliares").click(function () {
          var arrCodigos = new Array();
          var arrOrdens = new Array();

          $("input[type=hidden][name='CAX_IDRapido[]']").each(function () {
            arrCodigos.push($(this).val());
          });

          $("input[type=text][name='CAX_OrdemRapido[]']").each(function () {
            arrOrdens.push($(this).val());
          });

          $("#btnSalvarCadastrosAuxiliares").prop("disabled", true);

          $.ajax({
            url: $.trim($("#cadastros_auxiliares_salvar_ordem").val()),
            dataType: "json",
            cache: false,
            data: {
              CAX_ID: arrCodigos,
              CAX_Ordem: arrOrdens,
            },
            type: "POST",
          })
            .success(function (data) {
              //alert(data); return;
              $("#btnSalvarCadastrosAuxiliares").prop("disabled", false);
              $(".modal").modal("hide");

              if (data.error) {
                dialogAlert(strInformacao, data.error.msg, 6);
                return;
              }

              $.notify(data.mensagem, "success");
              return;
            })
            .fail(function (data) {
              //alert(data); return;
              $(".modal").modal("hide");
              $("#btnSalvarCadastrosAuxiliares").prop("disabled", false);
              dialogAlert(strAtencao, data.responseText, 6);
              return;
            });
        });
      }, 500);
      return;
    })
    .fail(function (data) {
      //alert(data); return;
      preLoadingClose();
      $("button").prop("disabled", false);
      $("#consultar-dados").html("");
      $(".modal").modal("hide");
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function salvarCarteirasDistrato() {
  var bolSubmeter = false;
  if ($.trim($("#SEL_SimNao5").val()) == "") {
    $.notify("Tipo de devolução precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#SEL_SimNao5").val()) == strSim) {
    if ($.trim($("#CAX_TipoDocumento_ID").val()) == "") {
      $.notify("Tipo de documento precisa ser informado.", "warn");
      return;
    } else if (
      $.trim($("#CTB_ValorDevolvido").val()) == "" ||
      $.trim($("#CTB_ValorDevolvido").val()) == "0"
    ) {
      $.notify("Valor devolvido precisa ser informado.", "warn");
      return;
    } else if (
      $.trim($("#CTB_QuantidadeParcelas").val()) == "" ||
      $.trim($("#CTB_QuantidadeParcelas").val()) == "0"
    ) {
      $.notify("Número de parcelas precisa ser informado.", "warn");
      return;
    } else if ($.trim($("#CTB_PrimeiroVencimento").val()) == "") {
      $.notify("Vencimento precisa ser informado.", "warn");
      return;
    }
  }

  arrDatasVencimentos = new Array();
  arrValoresParcelas = new Array();
  arrFormasPagamentos = new Array();

  $("input[type=date][name='SGP_DataVencimento[]']").each(function () {
    arrDatasVencimentos.push($(this).val());
  });

  $("input[type=text][name='SGP_ValorParcela3[]']").each(function () {
    arrValoresParcelas.push($(this).val());
  });

  $("select[name='SGP_FormaPagamento3[]'] option:selected").each(function () {
    arrFormasPagamentos.push($(this).val());
  });

  $("#btnSalvarDistrato").prop("disabled", true);
  var strLabel = $("#btnSalvarDistrato").html();
  $("#btnSalvarDistrato").html(strCarregando);

  $.ajax({
    url: $.trim($("#carteiras_contratos_distrato_salvar").val()),
    dataType: "json",
    cache: false,
    data: {
      CTO_ID: $.trim($("#CTO_ID").val()),
      CAX_ID: $.trim($("#CAX_TipoDocumento_ID").val()),
      CTO_SimNao: $.trim($("#SEL_SimNao5").val()),
      CTB_ValorDevolvido: $.trim($("#CTB_ValorDevolvido").val()),
      CTB_NumeroParcelas: $.trim($("#CTB_QuantidadeParcelas").val()),
      CTB_PrimeiroVencimento: $.trim($("#CTB_PrimeiroVencimento").val()),
      CTB_DatasVencimentos: arrDatasVencimentos,
      CTB_ValorParcela: arrValoresParcelas,
      CTB_FormaPagamento: arrFormasPagamentos,
    },
    type: "POST",
  })
    .success(function (data) {
      $("#btnSalvarDistrato").prop("disabled", false);
      $("#btnSalvarDistrato").html(strLabel);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $(".modal").modal("hide");
      $.notify(data.mensagem, "success");

      setTimeout(function () {
        redir("");
      }, 1000);
    })
    .fail(function (data) {
      $("#btnSalvarDistrato").prop("disabled", false);
      $("#btnSalvarDistrato").html(strLabel);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function getHistoricoInsumos(insumoID, fornecedorID, strTipo) {
  $.ajax({
    url: $.trim($("#insumos_historico").val()),
    dataType: "json",
    cache: false,
    data: {
      strTipo: $.trim(strTipo),
      INS_ID: $.trim(insumoID),
      ENT_ID: $.trim(fornecedorID),
    },
    type: "POST",
  })
    .success(function (data) {
      preLoadingClose();

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      dialogAlert(data.strTitulo, data.strHtml, 3);
    })
    .fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function resetChosen() {
  $(".chosen").chosen("destroy");
  $(".chosen").prop("selectedindex", -1);
  $(".chosen").chosen();
}

function initEntidades() {
  $(document).ready(function () {
    preLoadingOpen();
    resetChosen();

    $(".multiplos").multiselect(getOptions());
    $('a[data-toggle="tab"]').on("shown.bs.tab", function (e) {
      resetChosen();
    });

    if ($.trim($("#ENT_ImagemAnterior").val()) != "") {
      $(".pop").on("click", function () {
        $(".imagepreview").attr("src", $.trim($("#ENT_ImagemAnterior").val()));
        $("#modal-image").modal("show");
      });
    }

    $("#divCampos").hide();
    $("#SGP_CPFCNPJ").unmask();
    $("#dadosPessoaFisica").hide();
    $("#dadosPessoaJuridica").hide();
    $("#grp-cpfcnpj").hide();
    $("#grp-razao").hide();
    $("#grp-fantasia").hide();
    $("#li-aba-tributario").hide();

    $("#EMP_TipoPessoa").change(function () {
      $("#divCampos").hide();
      $("#SGP_CPFCNPJ").unmask();
      $("#dadosPessoaFisica").hide();
      $("#dadosPessoaJuridica").hide();
      $("#grp-cpfcnpj").hide();
      $("#grp-razao").hide();
      $("#grp-fantasia").hide();
      $("#li-aba-conjuge").hide();

      if (this.value == $("#hddFlagPessoaFisica").val()) {
        $("#divCampos").show();
        $("#lblCPFCNPJ").html(
          'CPF <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
        );
        $("#SGP_CPFCNPJ").mask("999.999.999-99");
        $("#SGP_CPFCNPJ").attr("placeholder", "Informe o CPF");
        $("#lbl-razao").html(
          'Nome <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
        );
        $("#lbl-fantasia").html("Apelido *");
        $("#ENT_RazaoSocial").attr("placeholder", "Informe o nome");
        $("#ENT_NomeFantasia").attr("placeholder", "Informe o apelido");
        $("#dadosPessoaJuridica").hide();
        $("#dadosPessoaFisica").show();
        $("#grp-cpfcnpj").show();
        $("#grp-razao").show();
        $("#grp-fantasia").show();

        resetChosen();
        exibirEntidadesAbaConjuge();

      } else if (this.value == $("#hddFlagPessoaJuridica").val()) {
        console.log('mmmmm');
        $("#divCampos").show();
        $("#lblCPFCNPJ").html(
          'CNPJ <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
        );
        $("#SGP_CPFCNPJ").mask("99.999.999/9999-99");
        $("#SGP_CPFCNPJ").attr("placeholder", "Informe o CNPJ");
        $("#lbl-razao").html(
          'Razão Social <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
        );
        $("#lbl-fantasia").html(
          'Nome Fantasia <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
        );
        $("#ENT_RazaoSocial").attr("placeholder", "Informe a razão social");
        $("#ENT_NomeFantasia").attr("placeholder", "Informe o nome fantasia");
        $("#dadosPessoaFisica").hide();
        $("#dadosPessoaJuridica").show();
        $("#grp-cpfcnpj").show();
        $("#grp-razao").show();
        $("#grp-fantasia").show();
      }else if (this.value == $("#hddFlagPessoaPassaporte").val()){
        console.log('xxxx');
        $("#divCampos").show();
        $("#lblCPFCNPJ").html('Número Passaporte <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
        $("#SGP_CPFCNPJ").attr("placeholder", "Informe o número do passaporte");
        $("#SGP_CPFCNPJ").attr('maxlength', '15');
        $("#lbl-razao").html('Nome <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
        $("#lbl-fantasia").html('Apelido <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>');
        $("#ENT_RazaoSocial").attr("placeholder", "Informe a nome");
        $("#ENT_NomeFantasia").attr("placeholder", "Informe o apelido");
        $("#dadosPessoaFisica, #dadosPessoaJuridica").hide();
        $("#grp-cpfcnpj").show();
        $("#grp-razao").show();
        $("#grp-fantasia").show();
      }else{
        console.log('qqqq');
        $(".chosen").trigger("chosen:updated");
      }
    });

    $("#TPE_ID").change(function () {
      exibirEntidadesAbaConjuge();

      $("#li-aba-tributario").hide();
      $("select[name='TPE_ID[]'] option:selected").each(function () {
        if ($(this).val() == 1 || $(this).val() == 2) {
          $("#li-aba-tributario").show();
          return;
        }
      });
    });

    $("#EMP_EstadoCivil").change(function () {
      if ($.trim(this.value) != "") {
        exibirEntidadesAbaConjuge();
      }
    });

    $("#CPP_TipoContaBancaria").change(function () {
      $(".camposContasBancariasEntidades, .camposContasBancariasEntidadesPIX")
        .css({ "pointer-events": "none", opacity: "0.4" })
        .attr("tabindex", "-1");

      if ($.trim(this.value) != "") {
        if ($.trim(this.value) == "X") {
          $(".camposContasBancariasEntidades")
            .css({ "pointer-events": "none", opacity: "0.4" })
            .attr("tabindex", "-1");
          $(".camposContasBancariasEntidadesPIX")
            .css({ "pointer-events": "visible", opacity: "1.0" })
            .attr("tabindex", "-1");
            $('#ECT_TipoChavePIX').chosen();
        } else {
          $(".camposContasBancariasEntidadesPIX")
            .css({ "pointer-events": "none", opacity: "0.4" })
            .attr("tabindex", "-1");
          $(".camposContasBancariasEntidades")
            .css({ "pointer-events": "visible", opacity: "1.0" })
            .attr("tabindex", "-1");
            
        }

        exibirEntidadesAbaConjuge();
      }
    });

    if ($.trim($("#ENT_ID").val()) != "") {
      $(".chosen").trigger("chosen:updated");
    }

    $("#EMP_TipoPessoa, #TPE_ID").trigger("change");

    preLoadingClose();
  });
}

function salvarEntidades() {
  if ($.trim($("#EMP_TipoPessoa").val()) == "") {
    $.notify("Tipo pessoa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#TPE_ID").val()) == "") {
    $.notify("Tipo de entidade precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#SGP_CPFCNPJ").val()) == "") {
    $.notify("CPF/CNPJ preicsa ser informado.", "warn");
    return;
  } else if ($.trim($("#ENT_RazaoSocial").val()) == "") {
    $.notify("Razão Social/Nome precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#ENT_NomeFantasia").val()) == "") {
    $.notify("Nome Fantasia/Apelido precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#hddEntidadeObgEndereco").val()) == strSim &&
    $.trim($("#TER_CEP").val()) == ""
  ) {
    $.notify("CEP precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#hddEntidadeObgEndereco").val()) == strSim &&
    $.trim($("#TER_Endereco").val()) == ""
  ) {
    $.notify("Endereço precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#hddEntidadeObgEndereco").val()) == strSim &&
    $.trim($("#TER_Numero").val()) == ""
  ) {
    $.notify("Número precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#hddEntidadeObgEndereco").val()) == strSim &&
    $.trim($("#TER_Bairro").val()) == ""
  ) {
    $.notify("Bairro precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#hddEntidadeObgEndereco").val()) == strSim &&
    $.trim($("#TER_Cidade").val()) == ""
  ) {
    $.notify("Cidade precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#hddEntidadeObgEndereco").val()) == strSim &&
    $.trim($("#UF_ID").val()) == ""
  ) {
    $.notify("Estado precisa ser informado.", "warn");
    return;
  } else {
    //Verifica os campos obrigatórios de acordo com o estado civil selecionado
    var validarEstadoCivil = verificaEstadoCivil($("#EMP_EstadoCivil").val());

    if (validarEstadoCivil == true && $.trim($("#ENC_CPF").val()) == "") {
      $.notify("CPF do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    } else if (
      validarEstadoCivil == true &&
      $.trim($("#ENC_Nome").val()) == ""
    ) {
      $.notify("Nome do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    } else if (
      validarEstadoCivil == true &&
      $.trim($("#ENC_Sexo").val()) == ""
    ) {
      $.notify("Sexo do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    } else if (
      validarEstadoCivil == true &&
      $.trim($("#UF_IDConjuge").val()) == ""
    ) {
      $.notify("Estado do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    } else if (
      $.trim($("#CAX_ID").val()) == "" &&
      $.trim($("#AEN_Descricao").val()) == "" &&
      $.trim($("#AEN_Arquivo").val()) == ""
    ) {
    } else if (
      $.trim($("#CAX_ID").val()) == "" ||
      $.trim($("#AEN_Descricao").val()) == "" ||
      $.trim($("#AEN_Arquivo").val()) == ""
    ) {
      $.notify("Todos os campos da aba Anexos devem ser preechidos.", "warn");
      $('.nav-tabs a[href="#tab-anexos"]').tab("show");
      return;
    }

    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnSalvar").html();
    $("#btnSalvar").html(strCarregando);
    preLoadingOpen();

    var arrDados = new FormData();

    //Cadastro
    var arrTipoEntidades = new Array();
    $("select[name='TPE_ID[]'] option:selected").each(function () {
      arrTipoEntidades.push($(this).val());
    });

    for (var i = 0; i < arrTipoEntidades.length; i++) {
      arrDados.append("TPE_ID[]", arrTipoEntidades[i]);
    }

    arrDados.append("ENT_ID", $.trim($("#ENT_ID").val()));
    arrDados.append("ENT_TipoPessoa", $.trim($("#EMP_TipoPessoa").val()));
    arrDados.append("ENT_CPFCNPJ", $.trim($("#SGP_CPFCNPJ").val()));
    arrDados.append("ENT_RazaoSocial", $.trim($("#ENT_RazaoSocial").val()));
    arrDados.append("ENT_NomeFantasia", $.trim($("#ENT_NomeFantasia").val()));
    arrDados.append("ENT_EstadoCivil", $.trim($("#EMP_EstadoCivil").val()));
    arrDados.append("ENT_Sexo", $.trim($("#EMP_Sexo").val()));
    arrDados.append("ENT_RG", $.trim($("#ENT_RG").val()));
    arrDados.append(
      "ENT_DataNascimento",
      $.trim($("#ENT_DataNascimento").val())
    );
    arrDados.append(
      "ENT_RG_OrgaoEmissor",
      $.trim($("#ENT_RG_OrgaoEmissor").val())
    );
    arrDados.append(
      "ENT_RG_OrgaoEmissor_UF",
      $.trim($("#ENT_RG_OrgaoEmissor_UF").val())
    );
    arrDados.append(
      "ENT_RG_DataExpedicao",
      $.trim($("#ENT_RG_DataExpedicao").val())
    );
    arrDados.append("ENT_Naturalidade", $.trim($("#ENT_Naturalidade").val()));
    arrDados.append("ENT_Nacionalidade", $.trim($("#ENT_Nacionalidade").val()));
    arrDados.append("ENT_CNAE", $.trim($("#ENT_CNAE").val()));
    arrDados.append(
      "ENT_InscricaoMunicipal",
      $.trim($("#ENT_InscricaoMunicipal").val())
    );
    arrDados.append(
      "ENT_InscricaoEstadual",
      $.trim($("#ENT_InscricaoEstadual").val())
    );
    arrDados.append("ENT_RepLegal", $.trim($("#ENT_RepLegal").val()));
    arrDados.append("ENT_CEP", $.trim($("#TER_CEP").val()));
    arrDados.append("ENT_Endereco", $.trim($("#TER_Endereco").val()));
    arrDados.append("ENT_Numero", $.trim($("#TER_Numero").val()));
    arrDados.append("ENT_Complemento", $.trim($("#TER_Complemento").val()));
    arrDados.append("ENT_Bairro", $.trim($("#TER_Bairro").val()));
    arrDados.append("ENT_Cidade", $.trim($("#TER_Cidade").val()));
    arrDados.append("UF_ID", $.trim($("#UF_ID").val()));
    arrDados.append("ENT_CodigoDARF", $.trim($("#ENT_CodigoDARF").val()));
    arrDados.append("CAX_Status_ID", $.trim($("#CAX_Status_ID").val()));
    arrDados.append(
      "ENT_CodigoContabilIntegracao",
      $.trim($("#ENT_CodigoContabilIntegracao").val())
    );
    arrDados.append("ENT_Profissao", $.trim($("#ENT_Profissao").val()));

    if ($("#ENT_Imagem").prop("files")[0] != undefined) {
      arrDados.append("ENT_Imagem", $("#ENT_Imagem").prop("files")[0]);
    }

    //Contatos
    arrDados.append("ENT_Contato", $.trim($("#ENT_Contato").val()));
    arrDados.append("ENT_Email", $.trim($("#ENT_Email").val()));
    arrDados.append("ENT_Telefone", $.trim($("#ENT_Telefone").val()));
    arrDados.append("ENT_Celular", $.trim($("#ENT_Celular").val()));
    arrDados.append("ENT_Fax", $.trim($("#ENT_Fax").val()));
    arrDados.append("ENT_Senha", $.trim($("#ENT_Senha").val()));

    //Banco
    arrDados.append("ECT_ID", $.trim($("#ECT_ID").val()));
    arrDados.append("BAN_ID", $.trim($("#BAN_ID").val()));
    arrDados.append("ECT_TipoConta", $.trim($("#CPP_TipoContaBancaria").val()));
    arrDados.append("ECT_Agencia", $.trim($("#ECT_Agencia").val()));
    arrDados.append("ECT_DvAgencia", $.trim($("#ECT_DvAgencia").val()));
    arrDados.append("ECT_Conta", $.trim($("#ECT_Conta").val()));
    arrDados.append("ECT_DvConta", $.trim($("#ECT_DvConta").val()));
    arrDados.append("ECT_TipoChavePIX", $.trim($("#ECT_TipoChavePIX").val()));
    arrDados.append("ECT_PIX", $.trim($("#ECT_PIX").val()));

    var strContaPrincipal = strNao;
    if ($("#ECT_ContaPrincipal").is(":checked")) {
      strContaPrincipal = strSim;
    }

    arrDados.append("ECT_ContaPrincipal", $.trim(strContaPrincipal));

    //Observações
    arrDados.append("ENT_Observacao", $.trim($("#ENT_Observacao").val()));

    //Conjuge
    arrDados.append("ENC_CPF", $.trim($("#ENC_CPF").val()));
    arrDados.append("ENC_Nome", $.trim($("#ENC_Nome").val()));
    arrDados.append("ENC_Sexo", $.trim($("#ENC_Sexo").val()));
    arrDados.append("ENC_RG", $.trim($("#ENC_RG").val()));
    arrDados.append(
      "ENC_DataNascimento",
      $.trim($("#ENC_DataNascimento").val())
    );
    arrDados.append(
      "ENC_RG_OrgaoEmissor",
      $.trim($("#ENC_RG_OrgaoEmissor").val())
    );
    arrDados.append("UF_OrgaoEmissor", $.trim($("#UF_OrgaoEmissor").val()));
    arrDados.append("ENC_Naturalidade", $.trim($("#ENC_Naturalidade").val()));
    arrDados.append("ENC_Nacionalidade", $.trim($("#ENC_Nacionalidade").val()));
    arrDados.append("ENC_Email", $.trim($("#ENC_Email").val()));
    arrDados.append("ENC_Telefone", $.trim($("#ENC_Telefone").val()));
    arrDados.append("ENC_Celular", $.trim($("#ENC_Celular").val()));
    arrDados.append("ENC_CEP", $.trim($("#ENC_CEP").val()));
    arrDados.append("ENC_Endereco", $.trim($("#ENC_Endereco").val()));
    arrDados.append("ENC_Numero", $.trim($("#ENC_Numero").val()));
    arrDados.append("ENC_Complemento", $.trim($("#ENC_Complemento").val()));
    arrDados.append("ENC_Bairro", $.trim($("#ENC_Bairro").val()));
    arrDados.append("ENC_Cidade", $.trim($("#ENC_Cidade").val()));
    arrDados.append("UF_IDConjuge", $.trim($("#UF_IDConjuge").val()));
		arrDados.append('ENC_DataEmissaoRG', $.trim($('#ENC_DataEmissaoRG').val()));
		arrDados.append('ENC_Profissao', $.trim($('#ENC_Profissao').val()));

    //Anexos
    if ($.trim($("#CAX_ID").val()) != "") {
      arrDados.append("CAX_ID", $.trim($("#CAX_ID").val()));
    }

    if ($.trim($("#AEN_Descricao").val()) != "") {
      arrDados.append("AEN_Descricao", $.trim($("#AEN_Descricao").val()));
    }

    if ($("#AEN_Arquivo").prop("files")[0] != undefined) {
      arrDados.append("AEN_Arquivo", $("#AEN_Arquivo").prop("files")[0]);
    }

    $.ajax({
      url: $.trim($("#entidades_salvar").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvar").html(strLabel);
        preLoadingClose();

        if (data.error != undefined) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        if (data.rapido == true) {
          $(".modal").modal("hide");
        }

        $.notify(data.mensagem, "success");

        if (data.redir != undefined) {
          setTimeout(function () {
            redir(data.redir);
          }, 1000);
        }
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvar").html(strLabel);
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function verificaEstadoCivil(valor) {
  if ($.trim(valor) != "") {
    var arrDados = new Array();
    arrDados.push("C", "U", "B", "O", "T", "L", "X", "Y");

    if (jQuery.inArray(valor, arrDados) !== -1) return true;
  }

  return false;
}

function exibirEntidadesAbaConjuge() {
  $("#li-aba-conjuge").hide();

  $("#ENC_CPF, #ENC_Nome, #ENC_Sexo, #UF_IDConjuge").attr("required", false);

  var arrTiposEntidades = new Array();
  $("select[name='TPE_ID[]'] option:selected").each(function () {
    arrTiposEntidades.push($(this).val());
  });

  if (
    arrTiposEntidades.length > 0 &&
    $.trim($("#EMP_TipoPessoa").val()) != "" &&
    $.trim($("#EMP_EstadoCivil").val()) != ""
  ) {
    $.ajax({
      url: $.trim($("#entidades_exibir_conjuge").val()),
      dataType: "json",
      cache: false,
      data: {
        EMP_TipoPessoa: $.trim($("#EMP_TipoPessoa").val()),
        TPE_ID: arrTiposEntidades,
        EMP_EstadoCivil: $.trim($("#EMP_EstadoCivil").val()),
      },
      type: "POST",
    }).success(function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#li-aba-conjuge").show();
        $("#ENC_CPF, #ENC_Nome, #ENC_Sexo, #UF_IDConjuge").attr(
          "required",
          true
        );
      }
      return;
    });
  }
}

function consultarEntidadesContasBancarias() {
  $(".btn-formulario").prop("disabled", true);
  $("#consultar-contas-bancarias").html(strCarregando);

  $(".camposContasBancariasEntidades, .camposContasBancariasEntidadesPIX")
    .css({ "pointer-events": "none", opacity: "0.4" })
    .attr("tabindex", "-1");

  $.ajax({
    url: $.trim($("#entidades_contas_bancarias_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      ENT_ID: $.trim($("#ENT_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        $("#consultar-contas-bancarias").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#consultar-contas-bancarias").html(data.strHtml);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#consultar-contas-bancarias").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function editarContasBancarias(contaID) {
  $(".btn-formulario").prop("disabled", true);
  $(".camposContasBancariasEntidades, .camposContasBancariasEntidadesPIX")
    .css({ "pointer-events": "none", opacity: "0.4" })
    .attr("tabindex", "-1");
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#entidades_contas_bancarias_editar").val()),
    dataType: "json",
    cache: false,
    data: {
      ECT_ID: $.trim(contaID),
      ENT_ID: $.trim($("#ENT_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        $("#consultar-contas-bancarias").html("");
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#ECT_ID").val(data.arrDados.ECT_ID);
      $("#BAN_ID").val(data.arrDados.BAN_ID);
      $("#CPP_TipoContaBancaria").val(data.arrDados.ECT_TipoConta);
      $("#ECT_Agencia").val(data.arrDados.ECT_Agencia);
      $("#ECT_DvAgencia").val(data.arrDados.ECT_DvAgencia);
      $("#ECT_Conta").val(data.arrDados.ECT_Conta);
      $("#ECT_DvConta").val(data.arrDados.ECT_DvConta);
      $("#ECT_TipoChavePIX").val(data.arrDados.ECT_TipoChavePIX);
      $("#ECT_PIX").val(data.arrDados.ECT_PIX);

      if (data.arrDados.ECT_ContaPrincipal == strSim) {
        $("#ECT_ContaPrincipal").bootstrapToggle("on");
      } else {
        $("#ECT_ContaPrincipal").bootstrapToggle("off");
      }

      $(".chosen").trigger("chosen:updated");

      if ($.trim(data.arrDados.ECT_PIX) != "") {
        $(".camposContasBancariasEntidades")
          .css({ "pointer-events": "none", opacity: "0.4" })
          .attr("tabindex", "-1");
        $(".camposContasBancariasEntidadesPIX")
          .css({ "pointer-events": "visible", opacity: "1.0" })
          .attr("tabindex", "-1");

          $("#ECT_TipoChavePIX").chosen();
      } else {
        $(".camposContasBancariasEntidadesPIX")
          .css({ "pointer-events": "none", opacity: "0.4" })
          .attr("tabindex", "-1");
        $(".camposContasBancariasEntidades")
          .css({ "pointer-events": "visible", opacity: "1.0" })
          .attr("tabindex", "-1");
      }
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#consultar-contas-bancarias").html("");

      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarContratosCarteirasAssociativo(e) {
  if (e.keyCode == 13) {
    consultarContratosCarteirasAssociativo();
  }
}

function consultarContratosCarteirasAssociativo() {
  var strLabel = consultarPadraoInicial();
  var arrEmpresas = new Array();
  var arrEstruturas = new Array();
  var arrEntidades = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='EST_ID[]'] option:selected").each(function () {
    arrEstruturas.push($(this).val());
  });

  $("select[name='ENT_ID[]'] option:selected").each(function () {
    arrEntidades.push($(this).val());
  });

  var strParcelasVinculadas = strNao;
  if ($("#CTO_ParcelasVinculadas").is(":checked")) {
    strParcelasVinculadas = strSim;
  }

  $.ajax({
    url: $.trim($("#carteiras_contratos_consultar_associativo").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: arrEmpresas,
      EST_ID: arrEstruturas,
      ENT_ID: arrEntidades,
      CTO_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      CTO_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
      CTO_Numero: $.trim($("#CTO_Numero2").val()),
      CTO_NumeroContratoBanco: $.trim($("#CTO_NumeroContratoBanco").val()),
      CTP_Associativo: strParcelasVinculadas,
    },
    type: "POST",
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);
      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarContratosCarteirasAssociativoParcelas() {
  var strLabel = consultarPadraoInicial();
  var arrEmpresas = new Array();
  var arrEstruturas = new Array();
  var arrContas = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='EST_ID[]'] option:selected").each(function () {
    arrEstruturas.push($(this).val());
  });

  $("select[name='CON_ID[]'] option:selected").each(function () {
    arrContas.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#carteiras_contratos_upload_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: arrEmpresas,
      EST_ID: arrEstruturas,
      CON_ID: arrContas,
      CCA_DataHoraCadastroInicial: $.trim($("#txtDataInicial").val()),
      CCA_DataHoraCadastroFinal: $.trim($("#txtDataFinal").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);
      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data, true);
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function carregarMensagensInternasNotificacoes() {
  $("#spnNotifications").html(strCarregandoIcone);
  $("#liNotificationsHeaderTotal").html(strCarregandoIcone);
  $("#listNotifications").html(strCarregandoIcone);

  $.ajax({
    url: $.trim($("#mensagens_notificacoes_consultar").val()),
    dataType: "json",
    cache: false,
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      if (data.error) {
        $("#consultar-dados").html("");
        $("#spnTotalRegistros").html("");
        //$.notify(data.error.msg, "error");
        return;
      }

      if (data.totalNotificacoes == 0) {
        data.totalNotificacoes = "";
      }

      $("#spnNotifications").html(data.totalNotificacoes);
      $("#liNotificationsHeaderTotal").html(data.mensagemTotalNotificacoes);
      $("#listNotifications").html(data.mensagemNotificacoes);
    })
    .fail(function (data) {
      //alert(data); return;
      $("#spnNotifications").html("");
      $("#liNotificationsHeaderTotal").html("");
      $("#listNotifications").html("");
      //$.notify(data.responseText, "error");
    });
}

function atualizarCarteirasContratoAssociativo(intContrato, intParcela, strCancelar){
  $('#btnAtualizar').prop('disabled', true);
  var strLabel = $('#btnAtualizar').html();
  $('#btnAtualizar').html(strCarregando);


  if ($.trim(strCancelar) != "") {
    $('input[name="CTP_Associativo[]"]').prop("checked", false);
  }

  $.ajax({
    url: $.trim($("#carteiras_contratos_atualizar_associativo").val()),
    dataType: "json",
    cache: false,
    data: {
      CTP_ID: $.trim($("input[name='CTP_Associativo[]']:checked").val()),
      CTO_ID: $.trim(intContrato),
      CTO_NumeroContratoBanco: $.trim($("#CTO_NumeroContratoBanco2").val()),
    },
    type: "POST",
  }).success(function (data) {
     $('#btnAtualizar').html(strLabel);
     $('#btnAtualizar').prop('disabled', false);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      consultarContratosCarteirasAssociativo();
      $(".modal").modal("hide");
      $.notify(data.mensagem, "success");

    }).fail(function (data) {
      $('#btnAtualizar').html(strLabel);
      $('#btnAtualizar').prop('disabled', false);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function selecionarFinanceiroAgruparPagamentos() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ENT_ID").val()) == "") {
    $.notify("Fornecedor precisa ser informado.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnSalvar").html();
    $("#btnSalvar, #consultar-parcelas").html(strCarregando);
    preLoadingOpen;

    $.ajax({
      url: $.trim($("#contas_pagar_agrupamentos_pagamentos_novo").val()),
      dataType: "json",
      cache: false,
      data: {
        EMP_ID: $.trim($("#EMP_ID").val()),
        ENT_ID: $.trim($("#ENT_ID").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvar").html(strLabel);
        preLoadingClose();

        if (data.error) {
          $("#consultar-parcelas").html("");
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $("#consultar-parcelas").html(data.strHtml);
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#consultar-parcelas").html("");
        $("#btnSalvar").html(strLabel);
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarAgruparPagamentosFinanceiro() {
  var strLabel = consultarPadraoInicial();
  var arrEmpresas = new Array();
  var arrFornecedores = new Array();
  var arrGerados = new Array();

  $("select[name='EMP_ID[]'] option:selected").each(function () {
    arrEmpresas.push($(this).val());
  });

  $("select[name='ENT_ID[]'] option:selected").each(function () {
    arrFornecedores.push($(this).val());
  });

  $("select[name='SEL_SimNao[]'] option:selected").each(function () {
    arrGerados.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#contas_pagar_agrupamentos_pagamentos_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: arrEmpresas,
      ENT_ID: arrFornecedores,
      CPG_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      CPG_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
      CPP_DataVencimentoInicial: $.trim($("#txtDataVencimentoInicial").val()),
      CPP_DataVencimentoFinal: $.trim($("#txtDataVencimentoFinal").val()),
      CAG_Gerado: arrGerados,
    },
    type: "POST",
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);
      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarFinanceiroAgruparPagamentos() {
  $(".btn-formulario").prop("disabled", true);
  preLoadingOpen();

  var arrSelecionados = new Array();

  $("input[type=checkbox][name='items[]']:checked").each(function () {
    arrSelecionados.push($(this).val());
  });

  if (arrSelecionados.length > 0) {
    $.ajax({
      url: $.trim($("#contas_pagar_agrupamentos_pagamentos_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        EMP_ID: $.trim($("#EMP_ID").val()),
        ENT_ID: $.trim($("#ENT_ID").val()),
        CPP_ID: arrSelecionados,
        CPG_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
        CPG_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        //alert(data); return;
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        selecionarFinanceiroAgruparPagamentos();
        $.notify(data.mensagem, "success");
        return;
      })
      .fail(function (data) {
        //alert(data); return;
        $(".btn-formulario").prop("disabled", false);
        //$('#consultar-parcelas').html('');
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
        return;
      });
  } else {
    $.notify(
      "Selecione no mínimo 1 (UM) título para gerar o agrupamento de pagamento.",
      "warn"
    );
    return;
  }
}

function gerarFinanceiroAgruparPagamentos() {
  if ($.trim($("#CON_ID").val()) == "") {
    $.notify("Conta bancária precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CPP_DataVencimento").val()) == "") {
    $.notify("Data vencimento precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CAX_ID").val()) == "") {
    $.notify("Tipo de documento precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CPG_NumeroDocumento").val()) == "") {
    $.notify("Número do documento precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CPG_DataEmissao").val()) == "") {
    $.notify("Data de emissão precisa ser informada.", "warn");
    return;
  } else {
    $("#btnConfirmar").prop("disabled", true);

    $.ajax({
      url:
        $.trim($("#contas_pagar_agrupamentos_pagamentos_gerar").val()) +
        "/" +
        $.trim($("#CAG_ID").val()),
      dataType: "json",
      cache: false,
      data: {
        CON_ID: $.trim($("#CON_ID").val()),
        CPP_DataVencimento: $.trim($("#CPP_DataVencimento").val()),
        CAX_ID: $.trim($("#CAX_ID").val()),
        CPG_NumeroDocumento: $.trim($("#CPG_NumeroDocumento").val()),
        CPG_DataEmissao: $.trim($("#CPG_DataEmissao").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        //alert(data); return;
        $("#btnConfirmar").prop("disabled", false);

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $(".modal").modal("hide");
        consultarAgruparPagamentosFinanceiro();
        $.notify(data.mensagem, "success");
        return;
      })
      .fail(function (data) {
        //alert(data); return;
        $("#btnConfirmar").prop("disabled", false);
        $(".modal").modal("hide");
        dialogAlert(strAtencao, data.responseText, 6);
        return;
      });
  }
}

function formularioComprasImportarXML() {
  $(".btn-filtro").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#documentos_upload_xml").val()),
    dataType: "json",
    cache: false,
    data: {
      valor: true,
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      $(".btn-filtro").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3);
      return;
    })
    .fail(function (data) {
      //alert(data); return;
      $(".btn-filtro").prop("disabled", false);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function salvarComprasImportarXML() {
  if ($.trim($("#DOC_Upload").val()) == "") {
    $.notify("Upload precisa ser informado.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);

    var arrDados = new FormData();

    arrDados.append("DOC_Upload", $("#DOC_Upload").prop("files")[0]);

    $.ajax({
      url: $.trim($("#documentos_importar_xml").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "post",
      success: function (data) {
        //alert(data); return;
        $(".btn-formulario").prop("disabled", false);
        $(".modal").modal("hide");

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        redir(data.diretorio, "parent");
      },
    }).fail(function (data) {
      //alert(data); return;
      $(".btn-formulario").prop("disabled", false);
      $(".modal").modal("hide");
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
  }
}

function exibirInsumosPedidosXContratos() {
  $(".btn-formulario").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#insumos_pesquisar_html").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: $.trim($("#EMP_ID").val()),
      ENT_ID: $.trim($("#ENT_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3);

      setTimeout(function () {
        $("#INS_PesquisarHTML").on("keyup", function (e) {
          if (e.keyCode == 13) {
            consultarInsumosPedidosXContratos();
          }
        });

        consultarInsumosPedidosXContratos();
      }, 1000);
      return;
    })
    .fail(function (data) {
      //alert(data); return;
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function consultarInsumosPedidosXContratos() {
  $("#consultar-dados-dialog").html(strCarregando);

  var arrItens = new Array();

  $("input[name='INS_ID[]']").each(function () {
    if ($.trim($(this).val()) != "") {
      arrItens.push($(this).val());
    }
  });

  $.ajax({
    url: $.trim($("#insumos_consultar_html").val()),
    dataType: "json",
    cache: false,
    data: {
      INS_Pesquisar: $.trim($("#INS_PesquisarHTML").val()),
      EMP_ID: $.trim($("#EMP_ID").val()),
      ENT_ID: $.trim($("#ENT_ID").val()),
      NFE_Quantidade: $.trim($("#hddNomeSelecionado").val()),
      NFE_Itens: arrItens,
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      $(".btn-filtro").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#consultar-dados-dialog").html(data.strHtml);
      return;
    })
    .fail(function (data) {
      //alert(data); return;
      $("#consultar-dados-dialog").html("");
      $(".btn-filtro").prop("disabled", false);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function selecionarInsumosPedidosXContratos(
  intItem,
  intNumero,
  strTipo,
  intChave
) {
  $("#" + $.trim($("#hddCodigoSelecionado").val())).val(
    $.trim(intItem) + "|" + strTipo
  );

  if ($.trim(strTipo) == "P") {
    var strURL =
      "../" +
      $.trim($("#hddClassPedidos").val()) +
      "/" +
      $.trim($("#hddAcaoVisualizar").val()) +
      "/" +
      intChave;
    var strNome = " (Pedido)";
  } else {
    var strURL =
      "../" +
      $.trim($("#hddClassContratos").val()) +
      "/" +
      $.trim($("#hddAcaoVisualizar").val()) +
      "/" +
      intChave;
    var strNome = " (Contrato)";
  }

  var strLink =
    "<a target='_blank' href='" +
    strURL +
    "' style='margin-right:5px;' data-toggle='tooltip' title='Clique aqui para visualizar pedido/medição'>" +
    intNumero +
    strNome +
    "</a>";

  $("#spn" + $.trim($("#hddCodigoSelecionado").val())).html($.trim(strLink));
  $("#hddCodigoSelecionado").val("");
  $(".btn-filtro").prop("disabled", false);
  $(".modal").modal("hide");
}

function salvarComprasDocumentosImportado() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ENT_ID").val()) == "") {
    $.notify("Fornecedor precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CAX_ID").val()) == "") {
    $.notify("Tipo do documento precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#DOC_Numero").val()) == "") {
    $.notify("Número do documento precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#DOC_DataEmissao").val()) == "") {
    $.notify("Data de emissão precisa ser informada.", "warn");
    return;
  } else {
    //Validando valores
    var arrQuantidades = new Array();
    var arrValoresUnitarios = new Array();
    var arrValoresTotais = new Array();
    var arrItens = new Array();
    var arrParcelasNumero = new Array();
    var arrParcelasVencimento = new Array();
    var arrParcelasValor = new Array();
    var arrPercentuaisIPI = new Array();
    var arrPercentuaisIIS = new Array();
    var arrPercentuaisICMS = new Array();

    $("input[name='DOI_Percentual_IPI[]']").each(function () {
      arrPercentuaisIPI.push($(this).val());
    });

    $("input[name='DOI_Percentual_IIS[]']").each(function () {
      arrPercentuaisIIS.push($(this).val());
    });

    $("input[name='DOI_Percentual_IIS[]']").each(function () {
      arrPercentuaisICMS.push($(this).val());
    });

    $("input[name='CPP_NumeroParcela[]']").each(function () {
      arrParcelasNumero.push($(this).val());
    });

    $("input[name='CPP_DataVencimento[]']").each(function () {
      arrParcelasVencimento.push($(this).val());
    });

    $("input[name='CPP_Valor[]']").each(function () {
      arrParcelasValor.push($(this).val());
    });

    $("input[name='INS_ID[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrItens.push($(this).val());
      }
    });

    $("input[name='DOI_Quantidade[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrQuantidades.push($(this).val());
      }
    });

    $("input[name='DOI_ValorUnitario[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrValoresUnitarios.push($(this).val());
      }
    });

    $("input[name='DOI_ValorTotal[]']").each(function () {
      if ($.trim($(this).val()) != "") {
        arrValoresTotais.push($(this).val());
      }
    });

    if ($("input[name='INS_ID[]']").length != arrItens.length) {
      $.notify("Você deve selecionar todos os itens do insumo.", "error");
      return;
    } else if (
      $("input[name='DOI_Quantidade[]']").length != arrQuantidades.length
    ) {
      $.notify("Quantidade dos itens da NF-e inválido.", "error");
      return;
    } else if (
      $("input[name='DOI_ValorUnitario[]']").length !=
      arrValoresUnitarios.length
    ) {
      $.notify("Valores unitários inválidos.", "error");
      return;
    } else if (
      $("input[name='DOI_ValorTotal[]']").length != arrValoresTotais.length
    ) {
      $.notify("Valores totais inválidos.", "error");
      return;
    }

    $(".btn-formulario").prop("disabled", true);

    var arrEmpresas = new Array();
    $("select[name='EMP_ID[]'] option:selected").each(function () {
      arrEmpresas.push($(this).val());
    });

    var arrDados = new FormData();

    arrDados.append("EMP_ID", $.trim($("#EMP_ID").val()));
    arrDados.append("ENT_ID", $.trim($("#ENT_ID").val()));
    arrDados.append("CAX_ID", $.trim($("#CAX_ID").val()));
    arrDados.append("DOC_Numero", $.trim($("#DOC_Numero").val()));
    arrDados.append("DOC_DataEmissao", $.trim($("#DOC_DataEmissao").val()));
    arrDados.append("DOC_Chave", $.trim($("#DOC_Chave").val()));
    arrDados.append("DOC_ValorFrete", $.trim($("#DOC_ValorFrete").val()));
    arrDados.append("DOC_ValorDesconto", $.trim($("#DOC_ValorDesconto").val()));
    arrDados.append(
      "DOC_ValorAcrescimo",
      $.trim($("#DOC_ValorAcrescimo").val())
    );
    arrDados.append("DOC_ValorTotal", $.trim($("#DOC_ValorTotal").val()));

    for (var i = 0; i < arrParcelasNumero.length; i++) {
      arrDados.append("arrParcelasNumero[]", arrParcelasNumero[i]);
    }

    for (var i = 0; i < arrParcelasVencimento.length; i++) {
      arrDados.append("arrParcelasVencimento[]", arrParcelasVencimento[i]);
    }

    for (var i = 0; i < arrParcelasValor.length; i++) {
      arrDados.append("arrParcelasValor[]", arrParcelasValor[i]);
    }

    for (var i = 0; i < arrItens.length; i++) {
      arrDados.append("ITEM[]", arrItens[i]);
    }

    for (var i = 0; i < arrQuantidades.length; i++) {
      arrDados.append("DOI_Quantidade[]", arrQuantidades[i]);
    }

    for (var i = 0; i < arrValoresUnitarios.length; i++) {
      arrDados.append("DOI_ValorUnitario[]", arrValoresUnitarios[i]);
    }

    for (var i = 0; i < arrValoresTotais.length; i++) {
      arrDados.append("DOI_ValorTotal[]", arrValoresTotais[i]);
    }

    for (var i = 0; i < arrPercentuaisIPI.length; i++) {
      arrDados.append("DOI_Percentual_IPI[]", arrPercentuaisIPI[i]);
    }

    for (var i = 0; i < arrPercentuaisIIS.length; i++) {
      arrDados.append("DOI_Percentual_IIS[]", arrPercentuaisIIS[i]);
    }

    for (var i = 0; i < arrPercentuaisICMS.length; i++) {
      arrDados.append("DOI_Percentual_ICMS[]", arrPercentuaisICMS[i]);
    }

    if ($("#DOC_GerarContasPagar").is(":checked")) {
      arrDados.append("DOC_GerarContasPagar", true);
    }

    $.ajax({
      url: $.trim($("#documentos_salvar_xml").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "post",
      success: function (data) {
        //alert(data); return;
        $(".btn-formulario").prop("disabled", false);
        $(".modal").modal("hide");

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        setTimeout(function () {
          redir(data.redir, "parent");
        }, 2000);
        return;
      },
    }).fail(function (data) {
      //alert(data); return;
      $(".btn-formulario").prop("disabled", false);
      $(".modal").modal("hide");
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
  }
}

function formularioFinanceiroImportarXML() {
  $(".btn-filtro").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#contas_pagar_upload_xml").val()),
    dataType: "json",
    cache: false,
    data: {
      valor: true,
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      $(".btn-filtro").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3);
      return;
    })
    .fail(function (data) {
      //alert(data); return;
      $(".btn-filtro").prop("disabled", false);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function salvarFinanceiroImportarXML() {
  if ($.trim($("#DOC_Upload").val()) == "") {
    $.notify("Upload precisa ser informado.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);

    var arrDados = new FormData();

    arrDados.append("DOC_Upload", $("#DOC_Upload").prop("files")[0]);

    $.ajax({
      url: $.trim($("#contas_pagar_importar_xml").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "post",
      success: function (data) {
        //alert(data); return;
        $(".btn-formulario").prop("disabled", false);
        $(".modal").modal("hide");

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        redir(data.diretorio, "parent");
      },
    }).fail(function (data) {
      //alert(data); return;
      $(".btn-formulario").prop("disabled", false);
      $(".modal").modal("hide");
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
  }
}

function salvarFinanceiroDocumentosImportado() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ENT_ID").val()) == "") {
    $.notify("Fornecedor precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CAX_ID").val()) == "") {
    $.notify("Tipo do documento precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#DOC_Numero").val()) == "") {
    $.notify("Número do documento precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#DOC_DataEmissao").val()) == "") {
    $.notify("Data de emissão precisa ser informada.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnSalvar").html();
    $("#btnSalvar").html(strCarregando);
    preLoadingOpen();

    //Validando valores
    var arrDados = new FormData();
    var arrQuantidades = new Array();
    var arrValoresUnitarios = new Array();
    var arrValoresTotais = new Array();
    var arrParcelasNumero = new Array();
    var arrParcelasVencimento = new Array();
    var arrParcelasValor = new Array();

    $("input[name='CPP_NumeroParcela[]']").each(function () {
      arrParcelasNumero.push($(this).val());
    });

    $("input[name='CPP_DataVencimento[]']").each(function () {
      arrParcelasVencimento.push($(this).val());
    });

    $("input[name='CPP_Valor[]']").each(function () {
      arrParcelasValor.push($(this).val());
    });

    arrDados.append("EMP_ID", $.trim($("#EMP_ID").val()));
    arrDados.append("ENT_ID", $.trim($("#ENT_ID").val()));
    arrDados.append("CAX_ID", $.trim($("#CAX_ID").val()));
    arrDados.append("DOC_Numero", $.trim($("#DOC_Numero").val()));
    arrDados.append("DOC_DataEmissao", $.trim($("#DOC_DataEmissao").val()));
    arrDados.append("DOC_Chave", $.trim($("#DOC_Chave").val()));
    arrDados.append("DOC_ValorFrete", $.trim($("#DOC_ValorFrete").val()));
    arrDados.append("DOC_ValorDesconto", $.trim($("#DOC_ValorDesconto").val()));
    arrDados.append(
      "DOC_ValorAcrescimo",
      $.trim($("#DOC_ValorAcrescimo").val())
    );
    arrDados.append("DOC_ValorTotal", $.trim($("#DOC_ValorTotal").val()));

    for (var i = 0; i < arrParcelasNumero.length; i++) {
      arrDados.append("arrParcelasNumero[]", arrParcelasNumero[i]);
    }

    for (var i = 0; i < arrParcelasVencimento.length; i++) {
      arrDados.append("arrParcelasVencimento[]", arrParcelasVencimento[i]);
    }

    for (var i = 0; i < arrParcelasValor.length; i++) {
      arrDados.append("arrParcelasValor[]", arrParcelasValor[i]);
    }

    $.ajax({
      url: $.trim($("#contas_pagar_salvar_xml").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "post",
      success: function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvar").html(strLabel);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        setTimeout(function () {
          redir(data.redir, "parent");
        }, 2000);
      },
    }).fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvar").html(strLabel);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
  }
}

function calcularCarteirasContratosParcelas() {
  $("#tdValorTotalParcelas").html(strCarregando);
  var arrParcelas = new Array();
  var arrValores = new Array();

  $("input[name='CSE_PercentualSerie[]']").each(function () {
    arrValores.push($(this).val());
  });

  $("input[name='CSE_QuantidadeParcelas[]']").each(function () {
    arrParcelas.push($(this).val());
  });

  if (arrValores.length > 0 && arrParcelas.length > 0) {
    $.ajax({
      url: $.trim($("#hddCarteiraCalcularValoresParcelas").val()),
      dataType: "json",
      cache: false,
      data: {
        arrValores: arrValores,
        arrParcelas: arrParcelas,
      },
      type: "POST",
    })
      .success(function (data) {
        //alert(data); return;
        preLoadingClose();

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $("#tdValorTotalParcelas").html(data.douValorTotal);
      })
      .fail(function (data) {
        //alert(data); return;
        $("#tdValorTotalParcelas").html("");
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });
  } else {
    $("#tdValorTotalParcelas").html("");
  }
}

function consultarPerfis() {
  $(".btn-filtro").prop("disabled", true);
  $("#spnTotalRegistrosConsultar").show();
  $("#spnTotalRegistrosConsultar").html(strCarregandoIcone);
  var strLabel = $("#btnFiltrar").html();
  $("#btnFiltrar, #consultar-dados").html(strCarregando);
  preLoadingOpen();

  var arrGruposEmpresas = new Array();
  var arrModulos = new Array();
  var arrRotas = new Array();
  var arrAcoes = new Array();

  $("select[name='GRE_ID[]'] option:selected").each(function () {
    arrGruposEmpresas.push($(this).val());
  });

  $("select[name='MOD_ID[]'] option:selected").each(function () {
    arrModulos.push($(this).val());
  });

  $("select[name='ROT_ID[]'] option:selected").each(function () {
    arrRotas.push($(this).val());
  });

  $("select[name='ACO_ID[]'] option:selected").each(function () {
    arrAcoes.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#perfis_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      GRE_ID: arrGruposEmpresas,
      MOD_ID: arrModulos,
      ROT_ID: arrRotas,
      ACO_ID: arrAcoes,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#btnFiltrar").html(strLabel);
      preLoadingClose();

      if (data.error) {
        $("#consultar-dados, #spnTotalRegistrosConsultar").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#consultar-dados").html(data.strHtml);
      $("#spnTotalRegistrosConsultar").html(data.totalRegistros);
      $("#pagination").html(data.pagination);
      $("#pagination").on("click", "a", function (e) {
        e.preventDefault();
        var pageno = $(this).attr("data-ci-pagination-page");
        loadPagination(data.url, pageno, data.arrFiltros);
      });
    })
    .fail(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#btnFiltrar").html(strLabel);
      $("#consultar-dados, #spnTotalRegistrosConsultar").html("");

      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarFinanceiroArquivoRetorno(e) {
  if (e.keyCode == 13) {
    consultarFinanceiroArquivoRetorno();
  }
}

function consultarFinanceiroArquivoRetorno() {
  var strLabel = consultarPadraoInicial();

  $.ajax({
    url: $.trim($("#financeiro_arquivo_retorno_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      EMP_ID: $.trim($("#EMP_ID").val()),
      CON_ID: $.trim($("#CON_ID").val()),
      RET_DataCadastroInicial: $.trim($("#txtDataInicial").val()),
      RET_DataCadastroFinal: $.trim($("#txtDataFinal").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      consultarPadraoSucesso(strLabel);
      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      consultarPadraoSucessoPaginacao(data);
    })
    .fail(function (data) {
      consultarPadraoFalha(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarFinanceiroArquivoRetorno() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CON_ID").val()) == "") {
    $.notify("Conta Bancária precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#RET_Arquivo").val()) == "") {
    $.notify("Arquivo de retorno precisa ser informado.", "warn");
    return;
  } else {
    preLoadingOpen();
    $("button").prop("disabled", true);

    var arrDados = new FormData();

    arrDados.append("EMP_ID", $.trim($("#EMP_ID").val()));
    arrDados.append("CON_ID", $.trim($("#CON_ID").val()));
    arrDados.append("RET_Arquivo", $("#RET_Arquivo").prop("files")[0]);

    $.ajax({
      url: $.trim($("#financeiro_arquivo_retorno_salvar").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "POST",
    })
      .success(function (data) {
        //alert(data); return;
        $("button").prop("disabled", false);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $("#EMP_ID, #CON_ID, #RET_Arquivo").val("");
        $("#EMP_ID, #CON_ID").trigger("chosen:updated");

        $.notify(data.mensagem, "success");

        if (data.executar !== undefined) {
          eval(data.executar);
        }
        return;
      })
      .fail(function (data) {
        //alert(data); return;
        preLoadingClose();
        $("button").prop("disabled", false);
        dialogAlert(strAtencao, data.responseText, 6);
        return;
      });
  }
}

function consultarFinanceiroParcelasPorContasPagarMultiplos() {
  $(".btn-filtro").prop("disabled", true);
  $("#CPP_NumeroParcela").multiselect("destroy");
  $("#CPP_NumeroParcela").html("");
  $("#consultar-parcelas").html("");

  var strHtml = "";

  $.ajax({
    url: $.trim($("#contas_pagar_parcelas_multiplos").val()),
    dataType: "json",
    cache: false,
    data: {
      CPG_Numero: $.trim($("#CPG_Numero").val()),
      CPP_DataVencimentoInicial: $.trim($("#txtDataInicial").val()),
      CPP_DataVencimentoFinal: $.trim($("#txtDataFinal").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      $(".btn-filtro").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      if (data.arrDados != undefined) {
        for (var i = 0; i < data.arrDados.length; i++) {
          strHtml +=
            "<option value='" +
            data.arrDados[i] +
            "'>" +
            data.arrDados[i] +
            "</option>";
        }
      }

      $("#CPP_NumeroParcela").append(strHtml);
      $("#CPP_NumeroParcela").multiselect("refresh");
      return;
    })
    .fail(function (data) {
      //alert(data); return;
      $("#CPP_NumeroParcela").multiselect("refresh");
      $(".btn-filtro").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function consultarCarteiraContratosParcelasBaixas() {
  $(".btn-filtro").prop("disabled", true);
  var strLabel = $("#btnSalvar").html();
  $("#btnSalvar, #consultar-parcelas").html(strCarregando);
  preLoadingOpen();

  var arrEntidades = new Array();
  var arrParcelas = new Array();

  $("select[name='ENT_ID[]'] option:selected").each(function () {
    arrEntidades.push($(this).val());
  });

  $("select[name='CPP_NumeroParcela[]'] option:selected").each(function () {
    arrParcelas.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#carteiras_contratos_parcelas_filtrar_baixas").val()),
    dataType: "json",
    cache: false,
    data: {
      CTO_Numero: $.trim($("#CTO_Numero").val()),
      ENT_ID: arrEntidades,
      CTP_Numero: arrParcelas,
      CTP_DataVencimentoInicial: $.trim($("#txtDataInicial").val()),
      CTP_DataVencimentoFinal: $.trim($("#txtDataFinal").val()),
      valor: true,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#btnSalvar").html(strLabel);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#consultar-parcelas").html(data.strHtml);
    })
    .fail(function (data) {
      $("#consultar-parcelas").html("");
      $(".btn-filtro").prop("disabled", false);
      $("#btnSalvar").html(strLabel);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function gerarPDFViabilidade() {
  preLoadingOpen();

  $("#divFluxoViabilidade").css("visibility", "hidden");
  $(".btn-formulario").css("visibility", "hidden");

  var pdf = new jsPDF("l", "in", "a4");

  pdf.internal.scaleFactor = 85;

  var options = {
    pagesplit: true,
  };

  pdf.addHTML($("#page-wrapper"), options, function () {
    pdf.save("Hiperdados.pdf");

    $("#divFluxoViabilidade").css("visibility", "visible");
    $(".btn-formulario").css("visibility", "visible");
    preLoadingClose();
  });
}

function gerarPDFEstudo() {
  preLoadingOpen();
  $(".btn-formulario").css("visibility", "hidden");
  $("#divVisualizacoes").css("visibility", "hidden");

  var pdf = new jsPDF("l", "in", "a4");

  pdf.internal.scaleFactor = 85;

  var options = {
    pagesplit: true,
    useCORS: true,
    format: "JPEG",
  };

  pdf.addHTML($("#page-wrapper"), options, function () {
    pdf.save("Hiperdados.pdf");

    $("#divVisualizacoes").css("visibility", "visible");
    $(".btn-formulario").css("visibility", "visible");
    preLoadingClose();
  });
}

function calcularFinanceiroContasPagarValorLiquido(valorLiquido, valorImposto) {
  $.ajax({
    url: $.trim($("#contas_pagar_parcelas_calcular").val()),
    dataType: "json",
    cache: false,
    data: {
      CPP_Valor: $.trim(valorLiquido),
      CPP_Imposto: $.trim(valorImposto),
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $("#CPP_ValorLiq2").val(data.douValorCalculado);
      return;
    })
    .fail(function (data) {
      //alert(data); return;
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function gerarFinanceiroContasPagarTitulo() {
  if ($.trim($("#EMP_Dialog_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ENT_Dialog_ID").val()) == "") {
    $.notify("Fornecedor precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CAX_Dialog_ID").val()) == "") {
    $.notify("Tipo de documento precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SGP_DataEmissao").val()) == "") {
    $.notify("Data de emissão precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#SGP_QuantidadeParcelas").val()) == "" || $.trim($("#SGP_QuantidadeParcelas").val()) == 0) {
    $.notify("Quantidade de parcelas precisa ser informada.", "warn");
    return;
  } else {
    //Se for igual a Gerar Título em Pedido de Compra
    if ($.trim($('#hddFlagComprasPedidosGerar').val()) == $.trim($('#hddAcaoEscondido').val())) {
      if ($.trim($("#SGP_Dialog_Numero").val()) == "") {
        $.notify("Número do documento precisa ser informado.", "warn");
        return;
      }
    }

    $("#btnGerarFinanceiroContasPagarTitulo").prop("disabled", true);
    var strLabel = $("#btnGerarFinanceiroContasPagarTitulo").html();
    $("#btnGerarFinanceiroContasPagarTitulo").html(strCarregando);

    var arrFormasPagamentos = new Array();
    var arrDatasVencimentos = new Array();
    var arrValoresParcelas = new Array();
    var arrLinhasDigitaveis = new Array();
    var arrBancos = new Array();
    var arrTiposContas = new Array();
    var arrAgencias = new Array();
    var arrDigitoAgencias = new Array();
    var arrContasCorrentes = new Array();
    var arrDigitoContaCorrentes = new Array();
    var arrPIX = new Array();

    $("select[name='BAN_ID3[]'] option:selected").each(function () {
      arrBancos.push($(this).val());
    });

    $("select[name='CPP_TipoContaBancaria3[]'] option:selected").each(
      function () {
        arrTiposContas.push($(this).val());
      }
    );

    $("input[name='CPP_Agencia3[]']").each(function () {
      arrAgencias.push($(this).val());
    });

    $("input[name='CPP_DvAgencia3[]']").each(function () {
      arrDigitoAgencias.push($(this).val());
    });

    $("input[name='CPP_ContaCorrente3[]']").each(function () {
      arrContasCorrentes.push($(this).val());
    });

    $("input[name='CPP_DvContaCorrente3[]']").each(function () {
      arrDigitoContaCorrentes.push($(this).val());
    });

    $("select[name='SGP_FormaPagamento3[]'] option:selected").each(function () {
      arrFormasPagamentos.push($(this).val());
    });

    $("input[type=date][name='SGP_DataVencimento[]']").each(function () {
      arrDatasVencimentos.push($(this).val());
    });

    $("input[type=text][name='SGP_ValorParcela3[]']").each(function () {
      arrValoresParcelas.push($(this).val());
    });

    $("input[type=text][name='CPP_LinhaDigitavel3[]']").each(function () {
      arrLinhasDigitaveis.push($(this).val());
    });

    $("input[type=text][name='SGP_Pix[]']").each(function () {
      arrPIX.push($(this).val());
    });

    $.ajax({
      url: $.trim($("#contas_pagar_gerar_titulo_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        SGP_Acao: $.trim($("#hddAcaoEscondido").val()),
        SGP_Numero: $.trim($("#SGP_Dialog_Numero").val()),
        COP_ID: $.trim($("#COP_Dialog_ID").val()),
        SGP_Identificador: $.trim($("#hddIdentificadorEscondido").val()),
        SGP_ID: $.trim($("#hddCodigoEscondido").val()),
        CPP_QuantidadeParcelas: $.trim($("#SGP_QuantidadeParcelas").val()),
        EMP_ID: $.trim($("#EMP_Dialog_ID").val()),
        ENT_ID: $.trim($("#ENT_Dialog_ID").val()),
        ENT_ID2: $.trim($("#ENT_Dialog_ID2").val()),
        CAX_ID: $.trim($("#CAX_Dialog_ID").val()),
        BAN_ID: arrBancos,
        CPP_TipoContaBancaria: arrTiposContas,
        CPP_Agencia: arrAgencias,
        CPP_DvAgencia: arrDigitoAgencias,
        CPP_ContaCorrente: arrContasCorrentes,
        CPP_DvContaCorrente: arrDigitoContaCorrentes,
        CPG_DataEmissao: $.trim($("#SGP_DataEmissao").val()),
        CPP_DataVencimento: arrDatasVencimentos,
        CPP_ValorParcela: arrValoresParcelas,
        CPP_LinhaDigitavel: arrLinhasDigitaveis,
        CPP_FormaPagamento: arrFormasPagamentos,
        CPP_PIX: arrPIX,
        SGP_ValorTotal: $.trim($("#hddValorEscondido").val()),
        SGP_Observacoes: $.trim($("#SGP_Observacoes").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnGerarFinanceiroContasPagarTitulo").prop("disabled", false);
        $("#btnGerarFinanceiroContasPagarTitulo").html(strLabel);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $(".modal").modal("hide");
        $.notify(data.mensagem, "success");

        setTimeout(function () {
          if (data.redir != undefined) {
            redir(data.redir, "parent");
          }

          if (data.executar != undefined) {
            eval(data.executar);
          }
        }, 1500);
      })
      .fail(function (data) {
        $("#btnGerarFinanceiroContasPagarTitulo").prop("disabled", false);
        $("#btnGerarFinanceiroContasPagarTitulo").html(strLabel);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function exibirLinhaDigitavelGerarTitulo(valor, html) {
  $("#" + html).hide();
  $("#btnGerarTituloCompras").prop("disabled", true);

  $.ajax({
    url: $.trim($("#contas_pagar_gerar_parcelas_forma_pagamento").val()),
    dataType: "json",
    cache: false,
    data: {
      SGP_Acao: $.trim($("#hddAcaoEscondido").val()),
      SGP_ID: $.trim($("#hddCodigoEscondido").val()),
      SGP_Identificador: $.trim($("#hddIdentificadorEscondido").val()),
      CPP_FormaPagamento: $.trim(valor),
      CPP_FormaPagamentoPIX: $.trim($("#hddFormaPagtoPix").val()),
      ENT_ID: $.trim($("#ENT_Dialog_ID2").val()),
    },
    type: "POST",
  })
    .success(function (data){
      $("#btnGerarTituloCompras").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      var strPIX = "SGP_Pix_" + justNumbers(html);

      $("#" + strPIX).hide();
      $("#" + strPIX).val(data.strPIX);
      if ($.trim($("#hddFormaPagtoPix").val()) == $.trim(valor)) {
        $("#" + strPIX).show();
      }

      if (data.exibirLinhaDigitavel != undefined) {
        $("#" + html).html(data.strHtml);
        $("#" + html).show();

        $(".maskLinhaDigitavel").mask(
          "99999.99999 99999.999999 99999.999999 9 99999999999999"
        );
        $(".maskLinhaDigitavelContaConsumo").mask(
          "999999999999 999999999999 999999999999 999999999999"
        );
      } else if (data.exibirConta != undefined) {
        $("#" + html).html(data.strHtml);
        $("#" + html).show();

        //Apenas números input css
        $(".numericOnly").on("keypress keyup blur", function (event) {
          $(this).val(
            $(this)
              .val()
              .replace(/[^A-Z\.][^0-9\.]/g, "")
          );
          if (
            (event.which != 46 || $(this).val().indexOf(".") != -1) &&
            (event.which < 48 || event.which > 57)
          ) {
            event.preventDefault();
          }
        });
      }
    })
    .fail(function (data) {
      $("#btnGerarTituloCompras").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function justNumbers(string) {
  var numsStr = string.replace(/[^0-9]/g, "");
  return parseInt(numsStr);
}

function atualizarObservacoesCarteiraContratos() {
  if ($.trim($("#CTO_ID").val()) != "") {
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#carteiras_contratos_atualizar_observacoes").val()),
      dataType: "json",
      cache: false,
      data: {
        CTO_ID: $.trim($("#CTO_ID").val()),
        CTO_Observacoes: $.trim($("#CTO_Observacoes").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        preLoadingClose();

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");
      })
      .fail(function (data) {
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function calcularJurosMultaCarteiraContratosParcelas() {
  $("#CPB_ValorJuros, #CPB_ValorMulta").val("");
  $("#btn-confirmar-sim, #btn-confirmar-nao").prop("disabled", true);

  if ($.trim($("#CPB_DataBaixa").val()) == "") {
    $.notify("Data da baixa precisa ser informada.", "warn");
    return;
  } else {
    $("#btn-confirmar-sim, #btn-confirmar-nao").prop("disabled", true);
    $("#CPB_ValorRecebido, #CPB_ValorJuros, #CPB_ValorMulta").prop(
      "disabled",
      true
    );

    $.ajax({
      url: $.trim($("#carteiras_contratos_calcular_juros_multas").val()),
      dataType: "json",
      cache: false,
      data: {
        CTO_ID: $.trim($("#CTO_ID").val()),
        CTP_ID: $.trim($("#hddParcelaID").val()),
        CPB_DataVencimento: $.trim($("#CPB_DataVencimento").val()),
        CPB_DataBaixa: $.trim($("#CPB_DataBaixa").val()),
      },
      type: "POST",
    }).success(function (data) {
        $("#btn-confirmar-sim, #btn-confirmar-nao").prop("disabled", false);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        if (data.douValorJuros !== undefined){
          $("#CPB_ValorJuros").val(data.douValorJuros);
        }

        if (data.douValorMulta !== undefined){
          $("#CPB_ValorMulta").val(data.douValorMulta);
        }

        /*if (data.douValorComJuros !== undefined){
          $("#CPB_ValorDesconto").trigger("blur");
        }*/

        if (data.douValorParcela !== undefined){
          $("#CPB_ValorRecebido").val(data.douValorParcela);
        }

        if (data.douValorTotal !== undefined){
          $("#CPB_ValorTotal").val(data.douValorTotal);
        }

        if (data.bloquear){
          if (data.situacao == data.parcial){
            $("#CPB_ValorRecebido").prop("disabled", false);
          }else{
            $("#CPB_ValorRecebido").val(data.douValorParcela);
            $("#CPB_ValorRecebido, #CPB_ValorJuros, #CPB_ValorMulta").prop("disabled", true);
          }
        }else{
          if (data.dias_atraso == 0) {
            $("#CPB_ValorRecebido, #CPB_ValorJuros, #CPB_ValorMulta").prop("disabled", false);
          }
        }

        carteiraBaixaCalcular();

        console.log('aaaaaa');
      }).fail(function (data) {
        $("#btn-confirmar-sim, #btn-confirmar-nao").prop("disabled", false);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function getVisibleArea() {
  preLoadingOpen();

  var rectangle = new google.maps.Rectangle({
    bounds: map.getBounds(),
  });

  //initialize_map();

  let visibleBounds = rectangle.getBounds();
  let nordeste = visibleBounds.getNorthEast();
  let sudoeste = visibleBounds.getSouthWest();
  let noroeste = new google.maps.LatLng(nordeste.lat(), sudoeste.lng());
  let sudeste = new google.maps.LatLng(sudoeste.lat(), nordeste.lng());

  let coordenadas = {
    nordesteLat: nordeste.lat(),
    nordesteLng: nordeste.lng(),
    sudesteLat: sudeste.lat(),
    sudesteLng: sudeste.lng(),
    sudoesteLat: sudoeste.lat(),
    sudoesteLng: sudoeste.lng(),
    noroesteLat: noroeste.lat(),
    noroesteLng: noroeste.lng(),
  };

  preLoadingClose();

  return coordenadas;
}

var polygons = [];
var polygonsInMap = [];

function showSigla(poly, event) {
  var contentString = "<b>" + poly.nome + "</b><br>";

  infoWindow.setContent(contentString);
  infoWindow.setPosition(event.latLng);

  infoWindow.open(map);
}

function montarZoneamento(coordenadas) {
  preLoadingOpen();
  limparZoneamento();

  $.ajax({
    url: $.trim($("#terrenos_zoneamento").val()),
    dataType: "json",
    cache: false,
    data: {
      coordenadas: coordenadas,
      TER_ID: $.trim($("#TER_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      $("#styleplanodiretort").prop("disabled", true);

      if (data.error) {
        preLoadingClose();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      var zoneamentoCoords = [];
      var zoneamento = [];

      for (let i = 0; i < data.zonas.length; i++) {
        polygonsInMap.push(data.zonas[i]);
        zoneamentoCoords[i] = [];

        for (let j = 0; j < data.zonas[i].poligono.length; j++) {
          let poly = data.zonas[i].poligono[j].split(",");
          zoneamentoCoords[i].push(
            new google.maps.LatLng(parseFloat(poly[0]), parseFloat(poly[1]))
          );
        }

        zoneamento[i] = new google.maps.Polygon({
          paths: zoneamentoCoords[i],
          id: data.zonas[i].id,
          strokeColor: data.zonas[i].cor,
          strokeOpacity: 1,
          strokeWeight: 2,
          fillColor: data.zonas[i].cor,
          fillOpacity: 0.7,
          /* 				disableDefaultUI: true,
        fullscreenControl: true,  */
          map: map,
        });

        polygons.push(zoneamento[i]);
      }

      for (let j = 0; j < polygons.length; j++) {
        polygons[j].setMap(map);

        google.maps.event.addListener(polygons[j], "click", function (event) {
          for (var k = 0; k < polygonsInMap.length; k++) {
            if (polygonsInMap[k].id == this.id) {
              showSigla(polygonsInMap[k], event);
            }
          }
        });
        infoWindow = new google.maps.InfoWindow();
      }

      $("#styleplanodiretort").html("Zoneamento");

      preLoadingClose();
    })
    .fail(function (data) {
      //alert(data); return;
      $("#styleplanodiretort").html("Zoneamento");
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function limparZoneamento() {
  for (let i = 0; i < polygonsZoneamento.length; i++) {
    polygonsZoneamento[i].setMap(null);
  }

  polygonsZoneamento = [];
  polygonsZoneamentoInMap = [];
}

function montarZoneamentoPorPoligono(coordenadas) {
  preLoadingOpen();
  $.ajax({
    url: $.trim($("#terrenos_zoneamento_poligono").val()),
    dataType: "json",
    cache: false,
    data: {
      coordenadas: coordenadas,
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      if (data.error) {
        preLoadingClose();
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      var zoneamentoCoords = [];
      var zoneamento = [];

      for (let i = 0; i < data.zonas.length; i++) {
        polygonsInMap.push(data.zonas[i]);
        zoneamentoCoords[i] = [];

        for (let j = 0; j < data.zonas[i].poligono.length; j++) {
          let poly = data.zonas[i].poligono[j].split(",");
          zoneamentoCoords[i].push(
            new google.maps.LatLng(parseFloat(poly[0]), parseFloat(poly[1]))
          );
        }

        zoneamento[i] = new google.maps.Polygon({
          paths: zoneamentoCoords[i],
          id: data.zonas[i].id,
          strokeColor: data.zonas[i].cor,
          strokeOpacity: 1,
          strokeWeight: 2,
          fillColor: data.zonas[i].cor,
          fillOpacity: 0.7,
          map: map,
        });

        polygons.push(zoneamento[i]);
      }

      for (let j = 0; j < polygons.length; j++) {
        polygons[j].setMap(map);

        google.maps.event.addListener(polygons[j], "click", function (event) {
          for (var k = 0; k < polygonsInMap.length; k++) {
            if (polygonsInMap[k].id == this.id) {
              showSigla(polygonsInMap[k], event);
            }
          }
        });
        infoWindow = new google.maps.InfoWindow();
      }

      preLoadingClose();
    })
    .fail(function (data) {
      //alert(data); return;
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function limparZoneamento() {
  for (let i = 0; i < polygons.length; i++) {
    polygons[i].setMap(null);
  }

  polygons = [];
}

function enterPesquisarInsumos(e) {
  if (e.keyCode == 13) {
    validarNovaSenha();
  }
}

function salvarCondicoesPagamentos() {
  if ($.trim($("#COP_Descricao").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else if (
    $.trim($("#COP_QuantidadeParcelas").val()) == "" ||
    $.trim($("#COP_QuantidadeParcelas").val()) == 0
  ) {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else {
    var arrCondicoes = new Array();
    var bolPreencheuTodos = true;

    $("input[name='COP_Condicao[]']").each(function () {
      if ($.trim($(this).val()) == "") {
        bolPreencheuTodos = false;
      }

      arrCondicoes.push($(this).val());
    });

    if (bolPreencheuTodos == false) {
      $.notify("Todas as condições precisam ser informadas.", "warn");
      return;
    }

    $(".btn-formulario").prop("disabled", true);
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#condicoes_pagamentos_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        COP_ID: $.trim($("#COP_ID").val()),
        COP_Descricao: $.trim($("#COP_Descricao").val()),
        COP_QuantidadeParcelas: $.trim($("#COP_QuantidadeParcelas").val()),
        COP_Condicao: arrCondicoes,
      },
      type: "POST",
    })
      .success(function (data) {
        //alert(data); return;
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();
        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        if ($.trim($("#COP_ID").val()) == "") {
          limparFormulario();
        }

        $.notify(data.mensagem, "success");
      })
      .fail(function (data) {
        //alert(data); return;
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
        return;
      });
  }
}

function initCondicoesPagamentos() {
  $(".multiplos").multiselect(getOptions());

  //keypress  blur
  $("#COP_QuantidadeParcelas").on("keyup", function (event) {
    $("#btnSalvar").prop("disabled", true);
    $("#divExibirCondicoes").html(strCarregando);

    if ($.trim($(this).val()) > 0) {
      $.ajax({
        url: $.trim($("#condicoes_pagamentos_parcelas").val()),
        dataType: "json",
        cache: false,
        data: {
          COP_ID: $.trim($("#COP_ID").val()),
          COP_QuantidadeParcelas: $.trim($(this).val()),
        },
        type: "POST",
      })
        .success(function (data) {
          //alert(data); return;
          if (data.error) {
            $("#divExibirCondicoes").html("");
            dialogAlert(strInformacao, data.error.msg, 6);
            return;
          }

          $("#divExibirCondicoes").html(data.strHtml);

          //Apenas números input css
          $(".numericOnly").on("keypress keyup blur", function (event) {
            $(this).val(
              $(this)
                .val()
                .replace(/[^A-Z\.][^0-9\.]/g, "")
            );
            if (
              (event.which != 46 || $(this).val().indexOf(".") != -1) &&
              (event.which < 48 || event.which > 57)
            ) {
              event.preventDefault();
            }
          });

          $("#btnSalvar").prop("disabled", false);
        })
        .fail(function (data) {
          //alert(data); return;
          $("#divExibirCondicoes").html("");
          dialogAlert(strAtencao, data.responseText, 6);
          return;
        });
    } else {
      $("#divExibirCondicoes").html("");
    }
  });

  $("#COP_QuantidadeParcelas").trigger("keyup");
}

function atualizarSolicitacoesDataPrevisao(itemSolicitacao, strData) {
  $.ajax({
    url: $.trim($("#solicitacoes_data_previsa_atualizar").val()),
    dataType: "json",
    cache: false,
    data: {
      SOL_ID: $.trim($("#SOL_ID").val()),
      SIT_ID: $.trim(itemSolicitacao),
      SIT_DataPrevisaoEntrega: $.trim(strData),
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }
    })
    .fail(function (data) {
      //alert(data); return;
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function atualizarPedidosDataPrevisao(itemPedido, strData) {
  $.ajax({
    url: $.trim($("#itens_pedidos_data_entrega_atualizar").val()),
    dataType: "json",
    cache: false,
    data: {
      PED_ID: $.trim($("#PED_ID").val()),
      PDI_ID: $.trim(itemPedido),
      PDI_DataPrevisaoEntrega: $.trim(strData),
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }
    })
    .fail(function (data) {
      //alert(data); return;
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function atualizarContratosDataPrevisao(itemContrato, strData) {
  $.ajax({
    url: $.trim($("#contratos_data_previsao_atualizar").val()),
    dataType: "json",
    cache: false,
    data: {
      CON_ID: $.trim($("#CON_ID").val()),
      ICT_ID: $.trim(itemContrato),
      ICT_DataPrevisaoEntrega: $.trim(strData),
    },
    type: "POST",
  })
    .success(function (data) {
      //alert(data); return;
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }
    })
    .fail(function (data) {
      //alert(data); return;
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function salvarFinanceiroContasPagar() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ENT_ID").val()) == "") {
    $.notify("Fornecedor precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CAX_ID").val()) == "") {
    $.notify("Tipo de documento precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CPG_NumeroDocumento").val()) == "") {
    $.notify("Número do documento precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CPG_DataEmissao").val()) == "") {
    $.notify("Data de emissão precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#CPG_Valor").val()) == "" ||
    $.trim($("#CPG_Valor").val()) == 0
  ) {
    $.notify("Valor total precisa ser informado ou maior que zero.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#contas_pagar_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        CPG_ID: $.trim($("#CPG_ID").val()),
        EMP_ID: $.trim($("#EMP_ID").val()),
        ENT_ID: $.trim($("#ENT_ID").val()),
        ENT_ID2: $.trim($("#ENT_ID2").val()),
        CAX_ID: $.trim($("#CAX_ID").val()),
        CPG_NumeroDocumento: $.trim($("#CPG_NumeroDocumento").val()),
        CPG_DataEmissao: $.trim($("#CPG_DataEmissao").val()),
        CPG_Valor: $.trim($("#CPG_Valor").val()),
        CPG_Desconto: $.trim($("#CPG_Desconto").val()),
        CPG_PercentualMulta: $.trim($("#CPG_PercentualMulta").val()),
        CPG_PercentualJuros: $.trim($("#CPG_PercentualJuros").val()),
        CPG_Observacao: $.trim($("#CPG_Observacao").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        //alert(data); return;
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        if ($.trim($("#CPG_ID").val()) == "") {
          setTimeout(function () {
            redir(data.redir, "parent");
          }, 1500);
        }
      })
      .fail(function (data) {
        //alert(data); return;
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function limparPoligonos() {
  limparZoneamento();
  limparDistrito();
}

function montarDistrito() {
  limparZoneamento();
  desenharDistrito();
}

function enterPesquisarEnderecosCadastros(e) {
  if (e.keyCode == 13) {
    consultarEnderecosCadastros();
  }
}

function consultarEnderecosCadastros() { }

function initEnderecosCadastros() {
  $(".selectpicker").selectpicker({
    width: "85%",
  });
  $(".selectpicker").selectpicker("refresh");
}

function pesquisarCidades(estadoID, cidadeSelecionada) {
  $("#SGP_Cidade").val("");

  $.ajax({
    url: $.trim($("#enderecos_pesquisar_cidades").val()),
    dataType: "json",
    cache: false,
    data: {
      UF_ID: estadoID,
      CID_ID: cidadeSelecionada,
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      if (data.arrDados.length) {
        $("#SGP_Cidade").val(data.arrDados[0].CID_Descricao);
      }
    })
    .fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function pesquisarBairros(cidadeID, bairroSelecionado) {
  $("#SGP_Bairro").val("");

  $.ajax({
    url: $.trim($("#enderecos_pesquisar_bairros").val()),
    dataType: "json",
    cache: false,
    data: {
      CID_ID: cidadeID,
      BAI_ID: bairroSelecionado,
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      if (data.arrDados.length) {
        $("#SGP_Bairro").val(data.arrDados[0].BAI_Descricao);
      }
    })
    .fail(function (data) {
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarEnderecosCadastrados() {
  if ($.trim($("#END_Descricao").val()) == "") {
    $.notify("Descrição do endereço precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SGP_CEP").val()) == "") {
    $.notify("CEP do endereço precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SGP_Endereco").val()) == "") {
    $.notify("Logradouro precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SGP_Numero").val()) == "") {
    $.notify("Número do endereço precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SGP_Cidade").val()) == "") {
    $.notify("Cidade do endereço precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#SGP_Bairro").val()) == "") {
    $.notify("Bairro do endereço precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#UF_ID").val()) == "") {
    $.notify("Estado do endereço precisa ser informado.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#enderecos_salvar").val()),
      dataType: "json",
      cache: false,
      data: $("#frmFormulario").serialize(),
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();

        if (data.error) {
          //dialogAlert(strInformacao, data.error.msg, 6);
          $.notify(data.error.msg, "error");
          return;
        }

        if ($.trim($("#END_ID").val()) == "") {
          limparFormulario();
        }

        $.notify(data.mensagem, "success");
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarEnderecosCadastrados() {
  $(".btn-filtro").prop("disabled", true);
  $("#consultar-dados").html(strCarregando);
  preLoadingOpen();

  var arrCadastroAuxiliares = new Array();
  var arrEstados = new Array();
  var arrStatus = new Array();

  $("select[name='CAX_ID[]'] option:selected").each(function () {
    arrCadastroAuxiliares.push($(this).val());
  });

  $("select[name='UF_ID[]'] option:selected").each(function () {
    arrEstados.push($(this).val());
  });

  $("select[name='SGP_Status[]'] option:selected").each(function () {
    arrStatus.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#enderecos_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      CAX_ID: arrCadastroAuxiliares,
      UF_ID: arrEstados,
      SGP_Pesquisar: $.trim($("#SGP_Pesquisar").val()),
      END_Status: arrStatus,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-filtro").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        $.notify(data.error.msg, "error");
        $("#consultar-dados").html("");
        return;
      }

      $("#consultar-dados").html(data.strHtml);

      if (data.totalRegistros > 0) {
        requireDataTables(false, true, true, true, true, false, true);
      }
    })
    .fail(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#consultar-dados").html("");
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarEnderecosCadastradosRapida(e) {
  if (e.keyCode == 13) {
    consultarEnderecosCadastradosRapido();
  }
}

function consultarEnderecosCadastradosRapido() {
  $("#divPesquisaRapida").html(strCarregando);

  $.ajax({
    url: $.trim($("#enderecos_pesquisar_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      END_Pesquisar: $.trim($("#END_PesquisaRapidaDialog").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        $("#divPesquisaRapida").html(data.error.msg);
        return;
      }

      $("#divPesquisaRapida").html(data.strHtml);
      $('[data-toggle="tooltip"]').tooltip({ html: true });
    })
    .fail(function (data) {
      $("#divPesquisaRapida").html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function selecionarEnderecoCadastrado(intCodigo, strDescricao) {
  $("#END_ID").val(intCodigo);
  $("#END_PesquisaRapida").val(strDescricao);
  $(".modal").modal("hide");
}

function desabilitarBotaoTipoPesquisaItemMedicao() {
  $("#MEI_Quantidade, #MEI_ValorTotal").prop("disabled", true);
  $("#MEI_Quantidade, #MEI_ValorTotal").val("");

  if ($("#rdbTipoPesquisa1").is(":checked")) {
    $("#MEI_Quantidade").prop("disabled", false);
  } else {
    $("#MEI_ValorTotal").prop("disabled", false);
  }
}

function calcularComercialPropostaTipoComissa(
  tipoCalculo,
  percentualComissao,
  valorComissao
) {
  var valor = $.trim($("#" + tipoCalculo).val());

  $("#" + percentualComissao).val("");
  $("#" + valorComissao).val("");
  $("#" + percentualComissao).prop("disabled", true);
  $("#" + valorComissao).prop("disabled", true);

  if (valor == "P") {
    $("#" + percentualComissao).prop("disabled", false);
    $("#" + valorComissao).prop("disabled", true);
  } else if (valor == "V") {
    $("#" + percentualComissao).prop("disabled", true);
    $("#" + valorComissao).prop("disabled", false);
  }
}

function calcularComissaoProposta(strTipo, douValor, douPercentual) {
  
  var valorproposta = $.trim($("#tdValorTotalProposta").html());

  if($.trim($("#UNI_Tipologia").val())=='L'){
    valorproposta = $.trim($("#tdValorTotalPropostaNominal").html());
  }

  $("#thTotalComissoesPercentualProposta, #thTotalComissoesValorProposta").html(
    strCarregando
  );

  var arrTotalPercentuais = new Array();
  var arrTotalValores = new Array();

  $("input[name='PRC_PercentualComissao[]']").each(function () {
    arrTotalPercentuais.push($(this).val());
  });

  $("input[name='PRC_ValorComissao[]']").each(function () {
    arrTotalValores.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#hddPropostasCalcularComissoes").val()),
    dataType: "json",
    cache: false,
    data: {
      PRC_TipoCalculo: $.trim($("#" + strTipo).val()),
      PRC_PercentualComissao: $.trim($("#" + douPercentual).val()),
      PRC_ValorComissao: $.trim($("#" + douValor).val()),
      PRO_ValorProposta: valorproposta,
      arrTotalPercentuais: arrTotalPercentuais,
      arrTotalValores: arrTotalValores,
    },
    type: "POST",
  })
    .success(function (data) {
      
      console.log(data);
      if (data.error) {
        //$.notify(data.error.msg, "error");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      if ($.trim($("#" + strTipo).val()) == "V") {
        $("#" + douPercentual).val(data.douValorCalculado);
      } else if ($.trim($("#" + strTipo).val()) == "P") {
        $("#" + douValor).val(data.douValorCalculado);
      }

      var intI = 0;
      var arrPercentuaisComissoes = new Array();
      var arrValoresComissoes = new Array();

      $("input[name='PRC_PercentualComissao[]']").each(function () {
        if ($.trim($(this).val()) != "") {
          arrPercentuaisComissoes.push($(this).val());
        }
      });

      $("input[name='PRC_ValorComissao[]']").each(function () {
        if ($.trim($(this).val()) != "") {
          arrValoresComissoes.push($(this).val());
        }
      });

      $.ajax({
        url: $.trim($("#propostas_calcular_total_comissoes").val()),
        dataType: "json",
        cache: false,
        data: {
          arrPercentuaisComissoes: arrPercentuaisComissoes,
          arrValoresComissoes: arrValoresComissoes,
          PRO_ValorProposta: $.trim($("#tdValorTotalProposta").html()),
        },
        type: "POST",
      })
        .success(function (data2) {
          if (data2.error) {
            $(
              "#thTotalComissoesPercentualProposta, #thTotalComissoesValorProposta"
            ).html("");
            dialogAlert(strAtencao, data2.error.msg, 6);
            return;
          }

          $("#thTotalComissoesPercentualProposta").html(
            data2.douPercentuaisComissoes
          );
          $("#thTotalComissoesValorProposta").html(data2.douValoresComissoes);
        })
        .fail(function (data2) {
          $("#thTotalComissoesPercentualProposta").html("");
          $("#thTotalComissoesValorProposta").html("");
          dialogAlert(strAtencao, data2.responseText, 6);
        });
    })
    .fail(function (data) {
      //$.notify(data.responseText, "error");
      $(
        "#thTotalComissoesPercentualProposta, #thTotalComissoesValorProposta"
      ).html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function adicionarNovaLinhaEntidades() {
  if ($.trim($("#CLI_Codigo").val()) == "") {
    $.notify("Cliente principal precisa ser informado.", "warn");
    return;
  }

  $("#btnAdicionarNovosCompradores").prop("disabled", true);
  var strHtml = $("#divEntidadesNovaLinha").html();

  if ($.trim(strHtml) == "") {
    var intSequencial = 1;
  } else {
    $("input[name='CLI_Multiplos_Codigo[]']").each(function () {
      intSequencial = this.id.replace("CLI_Multiplos_Codigo_", "");
    });

    intSequencial++;
  }

  $.ajax({
    url: $.trim($("#propostas_entidades_multiplos").val()),
    dataType: "json",
    cache: false,
    data: {
      GRE_ID: $.trim($("#GRE_ID").val()),
      SGP_Sequencia: $.trim(intSequencial),
    },
    type: "POST",
  })
    .success(function (data) {
      $("#btnAdicionarNovosCompradores").prop("disabled", false);

      if (data.error) {
        $("#divEntidadesNovaLinha").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#divEntidadesNovaLinha").html(strHtml + data.strHtml);

      //Entidades Multiplas = Pesquisar por código
      $("input[name='CLI_Multiplos_Codigo[]'").blur(function (e) {
        var intSequencial = this.id.replace("CLI_Multiplos_Codigo_", "");
        var valor = $.trim($("#" + this.id).val());

        if (valor != "") {
          $.ajax({
            url: $.trim($("#propostas_entidades_multiplos_filtrar").val()),
            dataType: "json",
            cache: false,
            data: {
              GRE_ID: $.trim($("#GRE_ID").val()),
              ENT_ID_Diferente: $.trim($("#CLI_ID").val()),
              ENT_Codigo: valor,
              TPE_ID: $.trim($("#TPE_ID").val()),
            },
            type: "POST",
          })
            .success(function (data2) {
              if (data2.error) {
                dialogAlert(strAtencao, data2.error.msg, 6);
                return;
              }

              if (data2.totalRegistros == 1) {
                $("#CLI_Multiplos_Codigo_" + intSequencial).val(
                  data2.arrDados[0].ENT_Codigo
                );
                $("#CLI_Multiplos_Pesquisar_" + intSequencial).val(
                  data2.arrDados[0].ENT_NomeFantasia
                );
                $("#CLI_Multiplos_ID_" + intSequencial).val(
                  data2.arrDados[0].ENT_ID
                );
              } else {
                $(
                  "#" + this.id,
                  "#CLI_Multiplos_Pesquisar_" + intSequencial,
                  "#CLI_Multiplos_ID_" + intSequencial
                ).val("");
              }
            })
            .fail(function (data2) {
              dialogAlert(strAtencao, data2.responseText, 6);
            });
        } else {
          $(
            "#" + this.id,
            "#CLI_Multiplos_Pesquisar_" + intSequencial,
            "#CLI_Multiplos_ID_" + intSequencial
          ).val("");
        }
      });

      //Entidades Multiplas = Pesquisar por nome
      $("input[name='CLI_Multiplos_Pesquisar[]'").blur(function (e) {
        var intSequencial = this.id.replace("CLI_Multiplos_Pesquisar_", "");
        var valor = $.trim($("#" + this.id).val());

        if (valor != "") {
          $.ajax({
            url: $.trim($("#propostas_entidades_multiplos_filtrar").val()),
            dataType: "json",
            cache: false,
            data: {
              GRE_ID: $.trim($("#GRE_ID").val()),
              ENT_ID_Diferente: $.trim($("#CLI_ID").val()),
              ENT_Pesquisar: valor,
              TPE_ID: $.trim($("#TPE_ID").val()),
            },
            type: "POST",
          })
            .success(function (data2) {
              if (data2.error) {
                dialogAlert(strAtencao, data2.error.msg, 6);
                return;
              }

              if (data2.totalRegistros == 1) {
                $("#CLI_Multiplos_Codigo_" + intSequencial).val(
                  data2.arrDados[0].ENT_Codigo
                );
                $("#CLI_Multiplos_Pesquisar_" + intSequencial).val(
                  data2.arrDados[0].ENT_NomeFantasia
                );
                $("#CLI_Multiplos_ID_" + intSequencial).val(
                  data2.arrDados[0].ENT_ID
                );
              } else {
                getFiltroPesquisarEntidades(intSequencial, valor);
              }
            })
            .fail(function (data2) {
              dialogAlert(strAtencao, data2.responseText, 6);
            });
        } else {
          $(
            "#CLI_Multiplos_Codigo_" + intSequencial,
            "#CLI_Multiplos_Pesquisar_" + intSequencial,
            "#CLI_Multiplos_ID_" + intSequencial
          ).val("");
        }
      });
    })
    .fail(function (data) {
      $("#btnAdicionarNovosCompradores").prop("disabled", false);
      $("#divEntidadesNovaLinha").html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function adicionarNovaEntidadeSequencial(intSequencial, intCodigo) {
  $.ajax({
    url: $.trim($("#propostas_entidades_multiplos_nova").val()),
    dataType: "json",
    cache: false,
    data: {
      GRE_ID: $.trim($("#GRE_ID").val()),
      ENT_ID: $.trim(intCodigo),
      ENT_ID_Diferente: $.trim($("#CLI_ID").val()),
      TPE_Descricao: $.trim($("#TPE_Descricao").val()),
      intSequencial: intSequencial,
      TPE_ID: $.trim($("#TPE_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        //$.notify(data.error.msg, "error");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3);

      setTimeout(function () {
        $("#hddCarregar").val("");
        $("#ENT_CPFCNPJ").unmask();
        $("#grp-cpfcnpj").hide();
        $("#grp-razao").hide();
        $("#grp-fantasia").hide();
        $("#dadosPessoaFisica").hide();
        $("#dadosPessoaJuridica").hide();
        $("#dadosConjuge").hide();

        $("#EMP_TipoPessoa").change(function () {
          $("#ENT_CPFCNPJ").unmask();
          $("#grp-cpfcnpj").hide();
          $("#grp-razao").hide();
          $("#grp-fantasia").hide();
          $("#ENT_CPFCNPJ").on('blur');

          if (this.value == $("#hddFlagPessoaFisica").val()) {
            $("#lblCPFCNPJ").html(
              'CPF <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
            );
            $("#ENT_CPFCNPJ").mask("999.999.999-99");
            $("#ENT_CPFCNPJ").attr("placeholder", "Informe o CPF");
            $("#lbl-razao").html(
              'Nome <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
            );
            $("#lbl-fantasia").html(
              'Apelido <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
            );
            $("#ENT_RazaoSocial").attr("placeholder", "Informe o nome");
            $("#grp-cpfcnpj").show();
            $("#grp-razao").show();
            $("#dadosPessoaFisica").show();
          } else if (this.value == $("#hddFlagPessoaJuridica").val()) {
            $("#lblCPFCNPJ").html(
              'CNPJ <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
            );
            $("#ENT_CPFCNPJ").mask("99.999.999/9999-99");
            $("#ENT_CPFCNPJ").attr("placeholder", "Informe o CNPJ");
            $("#lbl-razao").html(
              'Razão Social <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
            );
            $("#lbl-fantasia").html(
              'Nome Fantasia <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
            );
            $("#ENT_RazaoSocial").attr("placeholder", "Informe a razão social");
            $("#grp-cpfcnpj").show();
            $("#grp-razao").show();
            $("#dadosPessoaJuridica").show();
            $("#ENT_CPFCNPJ").off('blur');
          }
        });

        $("#EMP_EstadoCivil").change(function () {
          $("#dadosConjuge").hide();
          if (
            $.trim(this.value) != "" &&
            $.trim($("#EMP_TipoPessoa").val()) != ""
          ) {
            $.ajax({
              url: $.trim($("#entidades_exibir_conjuge_vendas").val()),
              dataType: "json",
              cache: false,
              data: {
                EMP_TipoPessoa: $.trim($("#EMP_TipoPessoa").val()),
                TPE_ID: $.trim($("#TPE_ID").val()),
                EMP_EstadoCivil: $.trim(this.value),
              },
              type: "POST",
            }).success(function (data) {
              //alert(data); return;
              if (data.sucesso == "true") {
                $("#dadosConjuge").show();
              }
              return;
            });
          }
        });

        if ($.trim($("#ENT_CPFCNPJ").val()) != "") {
          $("#EMP_TipoPessoa").trigger("change");
        }

        if ($.trim($("#EMP_EstadoCivil").val()) != "") {
          $("#EMP_EstadoCivil").trigger("change");
        }
      }, 500);
    })
    .fail(function (data) {
      //$.notify(data.responseText, "error");
      $(
        "#thTotalComissoesPercentualProposta, #thTotalComissoesValorProposta"
      ).html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarNovaEntidadePropostas() {
  if ($.trim($("#EMP_TipoPessoa").val()) == "") {
    $.notify("Tipo de pessoa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ENT_CPFCNPJ").val()) == "") {
    $.notify("CPF/CNPJ precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#ENT_Telefone").val()) == "" &&
    $.trim($("#ENT_Celular").val()) == ""
  ) {
    $.notify("Telefone ou celular precisa ser informado.", "warn");
    return;
  } else {
    //Verifica os campos obrigatórios de acordo com o estado civil selecionado
    var validarEstadoCivil = verificaEstadoCivil($("#EMP_EstadoCivil").val());

    if (validarEstadoCivil == true && $.trim($("#ENC_CPF").val()) == "") {
      $.notify("CPF do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    } else if (
      validarEstadoCivil == true &&
      $.trim($("#ENC_Nome").val()) == ""
    ) {
      $.notify("Nome do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    } else if (
      validarEstadoCivil == true &&
      $.trim($("#ENC_Sexo").val()) == ""
    ) {
      $.notify("Sexo do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    } else if (
      validarEstadoCivil == true &&
      $.trim($("#UF_IDConjuge").val()) == ""
    ) {
      $.notify("Estado do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    }

    var arrDados = new FormData();

    if ($("#UF_ID").val() != undefined) {
      if ($.trim($("#UF_ID").val()) == "") {
        $.notify("Estado precisa ser informado.", "error");
        return;
      }

      arrDados.append("UF_ID", $.trim($("#UF_ID").val()));
    }

    $(".btn-formulario").prop("disabled", true);
    $(".btn-formulario").html(strCarregando);

    var intSequencial = $.trim($("#intSequencial").val());

    arrDados.append("intSequencial", intSequencial);
    arrDados.append("GRE_ID", $.trim($("#GRE_ID").val()));
    arrDados.append("ENT_ID", $.trim($("#hddEntidade").val()));
    arrDados.append("TPE_ID", $.trim($("#TPE_ID").val()));
    arrDados.append("EMP_TipoPessoa", $.trim($("#EMP_TipoPessoa").val()));
    arrDados.append("ENT_CPFCNPJ", $.trim($("#ENT_CPFCNPJ").val()));
    arrDados.append("ENT_RazaoSocial", $.trim($("#ENT_RazaoSocial").val()));

    var strNomeFantasia = $.trim($("#ENT_NomeFantasia").val());
    if ($.trim(strNomeFantasia) == "") {
      strNomeFantasia = $.trim($("#ENT_RazaoSocial").val());
    }

    arrDados.append("ENT_NomeFantasia", strNomeFantasia);
    arrDados.append("ENT_Email", $.trim($("#ENT_Email").val()));
    arrDados.append("ENT_Telefone", $.trim($("#ENT_Telefone").val()));
    arrDados.append("ENT_Celular", $.trim($("#ENT_Celular").val()));

    if ($("#EMP_EstadoCivil").val() != undefined) {
      arrDados.append("ENT_EstadoCivil", $.trim($("#EMP_EstadoCivil").val()));
    }

    if ($("#ENT_RG").val() != undefined) {
      arrDados.append("ENT_RG", $.trim($("#ENT_RG").val()));
    }

    if ($("#ENT_DataNascimento").val() != undefined) {
      arrDados.append(
        "ENT_DataNascimento",
        $.trim($("#ENT_DataNascimento").val())
      );
    }

    if ($("#ENT_RG_OrgaoEmissor").val() != undefined) {
      arrDados.append(
        "ENT_RG_OrgaoEmissor",
        $.trim($("#ENT_RG_OrgaoEmissor").val())
      );
    }

    if ($("#EMP_Sexo").val() != undefined) {
      arrDados.append("ENT_Sexo", $.trim($("#EMP_Sexo").val()));
    }

    if ($("#UF_ID2").val() != undefined) {
      arrDados.append("ENT_RG_OrgaoEmissor_UF", $.trim($("#UF_ID2").val()));
    }

    if ($("#ENT_RG_DataExpedicao").val() != undefined) {
      arrDados.append(
        "ENT_RG_DataExpedicao",
        $.trim($("#ENT_RG_DataExpedicao").val())
      );
    }

    if ($("#ENT_Naturalidade").val() != undefined) {
      arrDados.append("ENT_Naturalidade", $.trim($("#ENT_Naturalidade").val()));
    }

    if ($("#ENT_Nacionalidade").val() != undefined) {
      arrDados.append(
        "ENT_Nacionalidade",
        $.trim($("#ENT_Nacionalidade").val())
      );
    }

    if ($("#ENT_CNAE").val() != undefined) {
      arrDados.append("ENT_CNAE", $.trim($("#ENT_CNAE").val()));
    }

    if ($("#ENT_InscricaoMunicipal").val() != undefined) {
      arrDados.append(
        "ENT_InscricaoMunicipal",
        $.trim($("#ENT_InscricaoMunicipal").val())
      );
    }

    if ($("#ENT_InscricaoEstadual").val() != undefined) {
      arrDados.append(
        "ENT_InscricaoEstadual",
        $.trim($("#ENT_InscricaoEstadual").val())
      );
    }

    if ($("#TER_CEP").val() != undefined) {
      arrDados.append("ENT_CEP", $.trim($("#TER_CEP").val()));
    }

    if ($("#TER_Endereco").val() != undefined) {
      arrDados.append("ENT_Endereco", $.trim($("#TER_Endereco").val()));
    }

    if ($("#TER_Numero").val() != undefined) {
      arrDados.append("ENT_Numero", $.trim($("#TER_Numero").val()));
    }

    if ($("#TER_Complemento").val() != undefined) {
      arrDados.append("ENT_Complemento", $.trim($("#TER_Complemento").val()));
    }

    if ($("#TER_Bairro").val() != undefined) {
      arrDados.append("ENT_Bairro", $.trim($("#TER_Bairro").val()));
    }

    if ($("#TER_Cidade").val() != undefined) {
      arrDados.append("ENT_Cidade", $.trim($("#TER_Cidade").val()));
    }

    //Conjuge
    if ($("#ENC_CPF").val() != undefined) {
      arrDados.append("ENC_CPF", $.trim($("#ENC_CPF").val()));
    }

    if ($("#ENC_Nome").val() != undefined) {
      arrDados.append("ENC_Nome", $.trim($("#ENC_Nome").val()));
    }

    if ($("#ENC_Email").val() != undefined) {
      arrDados.append("ENC_Email", $.trim($("#ENC_Email").val()));
    }

    if ($("#ENC_Sexo").val() != undefined) {
      arrDados.append("ENC_Sexo", $.trim($("#ENC_Sexo").val()));
    }

    if ($("#ENC_RG").val() != undefined) {
      arrDados.append("ENC_RG", $.trim($("#ENC_RG").val()));
    }

    if ($("#ENC_DataNascimento").val() != undefined) {
      arrDados.append(
        "ENC_DataNascimento",
        $.trim($("#ENC_DataNascimento").val())
      );
    }

    if ($("#ENC_RG_OrgaoEmissor").val() != undefined) {
      arrDados.append(
        "ENC_RG_OrgaoEmissor",
        $.trim($("#ENC_RG_OrgaoEmissor").val())
      );
    }

    if ($("#UF_OrgaoEmissor").val() != undefined) {
      arrDados.append("UF_OrgaoEmissor", $.trim($("#UF_OrgaoEmissor").val()));
    }

    if ($("#ENC_Naturalidade").val() != undefined) {
      arrDados.append("ENC_Naturalidade", $.trim($("#ENC_Naturalidade").val()));
    }

    if ($("#ENC_Nacionalidade").val() != undefined) {
      arrDados.append(
        "ENC_Nacionalidade",
        $.trim($("#ENC_Nacionalidade").val())
      );
    }

    if ($("#ENC_Telefone").val() != undefined) {
      arrDados.append("ENC_Telefone", $.trim($("#ENC_Telefone").val()));
    }

    if ($("#ENC_Celular").val() != undefined) {
      arrDados.append("ENC_Celular", $.trim($("#ENC_Celular").val()));
    }

    if ($("#ENC_CEP").val() != undefined) {
      arrDados.append("ENC_CEP", $.trim($("#ENC_CEP").val()));
    }

    if ($("#ENC_Endereco").val() != undefined) {
      arrDados.append("ENC_Endereco", $.trim($("#ENC_Endereco").val()));
    }

    if ($("#ENC_Numero").val() != undefined) {
      arrDados.append("ENC_Numero", $.trim($("#ENC_Numero").val()));
    }

    if ($("#ENC_Complemento").val() != undefined) {
      arrDados.append("ENC_Complemento", $.trim($("#ENC_Complemento").val()));
    }

    if ($("#ENC_Bairro").val() != undefined) {
      arrDados.append("ENC_Bairro", $.trim($("#ENC_Bairro").val()));
    }

    if ($("#ENC_Cidade").val() != undefined) {
      arrDados.append("ENC_Cidade", $.trim($("#ENC_Cidade").val()));
    }

    if ($("#UF_IDConjuge").val() != undefined) {
      arrDados.append("UF_IDConjuge", $.trim($("#UF_IDConjuge").val()));
    }

    $.ajax({
      url: $.trim($("#propostas_entidades_multiplos_salvar").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "post",
      success: function (data) {
        //alert(data); return;
        $(".btn-formulario").prop("disabled", false);

        if (data.error) {
          //$.notify(data.error.msg, "error");
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $("#CLI_Multiplos_ID_" + intSequencial).val(data.ENT_ID);
        $("#CLI_Multiplos_Codigo_" + intSequencial).val(data.ENT_Codigo);
        $("#CLI_Multiplos_Pesquisar_" + intSequencial).val(
          data.ENT_NomeFantasia
        );

        $(".modal").modal("hide");
        $.notify(data.mensagem, "success");
      },
    }).fail(function (data) {
      //alert(data); return;
      $(".btn-formulario").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
  }
}

function getFiltroPesquisarEntidades(intSequencial, strPesquisar) {
  $.ajax({
    url: $.trim($("#propostas_entidades_multiplos_pesquisar").val()),
    dataType: "json",
    cache: false,
    data: {
      ENT_ID_Diferente: $.trim($("#CLI_ID").val()),
      ENT_Pesquisar: $.trim(strPesquisar),
      TPE_Descricao: $.trim($("#TPE_Descricao").val()),
      intSequencial: intSequencial,
      TPE_ID: $.trim($("#TPE_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        //$.notify(data.error.msg, "error");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3);

      if ($.trim(strPesquisar) != "") {
        setTimeout(function () {
          consultarEntidadesMultiplas();
        }, 1500);
      }
    })
    .fail(function (data) {
      //$.notify(data.responseText, "error");
      $(
        "#thTotalComissoesPercentualProposta, #thTotalComissoesValorProposta"
      ).html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarEntidades(e) {
  if (e.keyCode == 13) {
    consultarEntidadesMultiplas();
  }
}

function consultarEntidadesMultiplas() {
  $("#divPesquisarEntidades").html(strCarregando);

  $.ajax({
    url: $.trim($("#propostas_entidades_multiplos_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      GRE_ID: $.trim($("#GRE_ID").val()),
      ENT_ID_Diferente: $.trim($("#CLI_ID").val()),
      TPE_Descricao: $.trim($("#TPE_Descricao").val()),
      SGP_Pesquisar: $.trim($("#SGP_Pesquisar_Entidade").val()),
      intSequencial: $.trim($("#SGP_Pesquisar_Sequencial").val()),
      TPE_ID: $.trim($("#TPE_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        $("#divPesquisarEntidades").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#divPesquisarEntidades").html(data.strHtml);
    })
    .fail(function (data) {
      $("#divPesquisarEntidades").html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function selecionarEntidade(
  intSequencial,
  intIdentificador,
  intCodigo,
  strNome
) {
  $("#SGP_Pesquisar_Entidade, #SGP_Pesquisar_Sequencial").val("");
  $("#CLI_Multiplos_ID_" + intSequencial).val(intIdentificador);
  $("#CLI_Multiplos_Codigo_" + intSequencial).val(intCodigo);
  $("#CLI_Multiplos_Pesquisar_" + intSequencial).val(strNome);
  $(".modal").modal("hide");
}

function salvarParametrosGeral() {
  $(".btn-formulario").prop("disabled", true);
  preLoadingOpen();

  var arrDados = new FormData();

  var strSelecionado = strNao;
  var strSelecionadoEndereco = strNao;

  if ($("#GRE_ImagemCheck").is(":checked")) {
    strSelecionado = strSim;
  }

  if ($("#GRE_ObrigatoriedadeEndereco").is(":checked")) {
    strSelecionadoEndereco = strSim;
  }

  arrDados.append("GRE_ImagemCheck", $.trim(strSelecionado));
  arrDados.append(
    "GRE_ObrigatoriedadeEndereco",
    $.trim(strSelecionadoEndereco)
  );

  if ($("#GRE_Imagem").prop("files")[0] != undefined) {
    arrDados.append("GRE_Imagem", $("#GRE_Imagem").prop("files")[0]);
  }

  arrDados.append("GRE_Telefone", $.trim($("#GRE_Telefone").val()));

  $.ajax({
    url: $.trim($("#grupos_empresas_parametros_gerais").val()),
    dataType: "json",
    cache: false,
    contentType: false,
    processData: false,
    data: arrDados,
    type: "post",
    success: function (data) {
      preLoadingClose();
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      if ($("#GRE_ImagemCheck").is(":checked")) {
        $("#exibirImagem").attr("src", data.GRE_Imagem);
      }

      $("#imgImagemGRE").prop("src", data.GRE_Imagem);
      $.notify(data.mensagem, "success");
    },
  }).fail(function (data) {
    $(".btn-formulario").prop("disabled", false);
    dialogAlert(strAtencao, data.responseText, 6);
    return;
  });
}

function salvarParametrosFinanceiro() {
  $(".btn-formulario").prop("disabled", true);
  var strLabel = $("#btnSalvarParametrosFinanceiro").html();
  $("#btnSalvarParametrosFinanceiro").html(strCarregando);
  preLoadingOpen();

  var strSelecionado                  = strNao;
  var strSelecionado2                 = strNao;
  var strUtilizarAlcada               = strNao;
  var strUtilizarAlcadaContasReceber  = strNao;
  var strApropriacoesAprovarEditar    = strNao;
  var strApropriacoesAdiantamentos    = strNao;
  var strGerarTituloCondicaoPagamento = strNao;
  var strRestringePlanoFinanceiro     = strNao;
  var strOrcamento                    = strNao;
  var strApropriacoesPrevisoes        = strNao;
  var strRestringirCentroCustos       = strNao;

  if ($("#PAR_ApropriacaoObrigatoria").is(":checked")) {
    strSelecionado = strSim;
  }

  if ($("#PAR_GerarImpostoAprovacao").is(":checked")) {
    strSelecionado2 = strSim;
  }

  if ($("#PAR_UtilizarAlcada").is(":checked")) {
    strUtilizarAlcada = strSim;
  }

  if ($("#PAR_UtilizarAlcadaContasReceber").is(":checked")) {
    strUtilizarAlcadaContasReceber = strSim;
  }

  if ($("#PAR_ApropriacoesAprovarEditar").is(":checked")) {
    strApropriacoesAprovarEditar = strSim;
  }

  if ($("#PAR_ApropriacaoObrigatoriaAdiantamentos").is(":checked")) {
    strApropriacoesAdiantamentos = strSim;
  }

  if ($("#PAR_GerarContasPagarCondicaoPagamentoObrigatoria").is(":checked")) {
    strGerarTituloCondicaoPagamento = strSim;
  }

  if ($("#PAR_RestringePlanoFinanceiro").is(":checked")) {
    strRestringePlanoFinanceiro = strSim;
  }

  if ($("#PAR_OrcamentoObrigatoria").is(":checked")) {
    strOrcamento = strSim;
  }

  if ($("#PAR_ApropriacaoObrigatoriaPrevisoes").is(":checked")) {
    strApropriacoesPrevisoes = strSim;
  }

  if ($("#PAR_RestringirCentroCusto").is(":checked")) {
    strRestringirCentroCustos = strSim;
  }

  $.ajax({
    url: $.trim($("#grupos_empresas_parametros_financeiro").val()),
    dataType: "json",
    cache: false,
    data: {
      PAR_IRRF_ENT_ID: $.trim($("#PAR_IRRF_ENT_ID").val()),
      PAR_ISS_EN_TID: $.trim($("#PAR_ISS_EN_TID").val()),
      PAR_INSS_ENT_ID: $.trim($("#PAR_INSS_ENT_ID").val()),
      PAR_CSRS_ENT_ID: $.trim($("#PAR_CSRS_ENT_ID").val()),
      CAX_Chosen_ID: $.trim($("#CAX_Chosen_ID").val()),
      PAR_TipoCaucao_CAX_ID: $.trim($("#PAR_TipoCaucao_CAX_ID").val()),
      PAR_ApropriacaoObrigatoria: $.trim(strSelecionado),
      PAR_GerarImpostoAprovacao: $.trim(strSelecionado2),
      PAR_UtilizarAlcada: $.trim(strUtilizarAlcada),
      PAR_ApropriacoesAprovarEditar: $.trim(strApropriacoesAprovarEditar),
      PAR_ApropriacaoObrigatoriaAdiantamentos: $.trim(strApropriacoesAdiantamentos),
      PAR_GerarContasPagarCondicaoPagamentoObrigatoria: $.trim(strGerarTituloCondicaoPagamento),
      PAR_UtilizarAlcadaContasReceber: $.trim(strUtilizarAlcadaContasReceber),
      PAR_PercentualMaximoPermitidoContasReceber: $.trim($("#PAR_PercentualMaximoPermitidoContasReceber").val()),
      PAR_DiasVencimentoCaucao: $.trim($("#PAR_DiasVencimentoCaucao").val()),
      PAR_RestringePlanoFinanceiro: $.trim(strRestringePlanoFinanceiro),
      PAR_Orcamento: $.trim(strOrcamento),
      PAR_ApropriacaoObrigatoriaPrevisoes: $.trim(strApropriacoesPrevisoes),
      PAR_RestringirCentroCusto: $.trim(strRestringirCentroCustos)
    },
    type: "POST",
  }).success(function (data) {
    $(".btn-formulario").prop("disabled", false);
    $("#btnSalvarParametrosFinanceiro").html(strLabel);
    preLoadingClose();

    if (data.error) {
      dialogAlert(strAtencao, data.error.msg, 6);
      return;
    }

    $.notify(data.mensagem, "success");
  }).fail(function (data) {
    $(".btn-formulario").prop("disabled", false);
    $("#btnSalvarParametrosFinanceiro").html(strLabel);
    preLoadingClose();

    dialogAlert(strAtencao, data.responseText, 6);
  });
}

function salvarParametrosCarteira() {
  if ($.trim($('#PAR_ApresentarValorParcela').val()) == ''){
    $.notify('Apresentar valor parcela precisa ser informado.', "warn");  
    return;
  }

  $(".btn-formulario").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#grupos_empresas_parametros_carteira").val()),
    dataType: "json",
    cache: false,
    data: {
      CEN_ID: $.trim($("#CEN_ID").val()),
      PLF_ContaContrato: $.trim($("#PLF_ContaContrato").val()),
      PLF_ContaDistrato: $.trim($("#PLF_ContaDistrato").val()),
      PAR_ReajusteNegativo: $.trim($("#PAR_ReajusteNegativo").val()),
      PAR_ApresentarValorParcela: $.trim($('#PAR_ApresentarValorParcela').val()),
      PAR_AlteraVencimento: $.trim($('#PAR_AlteraVencimento').val())
    },
    type: "POST",
  }).success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $.notify(data.mensagem, "success");
    }).fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarParametrosCompras() {
  $(".btn-formulario").prop("disabled", true);
  var strLabel = $("#btnSalvarCompras").html();
  $("#btnSalvarCompras").html(strCarregando);
  preLoadingOpen();

  var strAprovarSolicitacao = strNao;
  var strAprovarPedido = strNao;
  var strAprovarContrato = strNao;
  var strAnexoObrigatorio = strNao;
  var strSolicitacaoDataEntregaObrigatorio = strNao;
  var strCotacoesCondicaoPagamentoObrigatoria = strNao;
  var strApropriacoesCompras = strNao;
  var strAlcadaCompras = strNao;
  var strBloquearValorOrcamentos = strNao;
  var strApropriacoesSolicitacoesAprovarEditar = strNao;
  var strApropriacoesPedidosAprovarEditar = strNao;
  var strApropriacoesContratosAprovarEditar = strNao;
  var strTravaCondicaoPagamentoDocumentos = strNao;
  var strGerarPedidoLivreContrato = strNao;
  var strAprovacaoCotacao = strNao;
  var strOrcamento = strNao;
  var strObservacaoCotacao = strNao;
  var strPedidoEntregue = strNao;

  if ($("#PAR_AprovaSolicitacao").is(":checked")) {
    strAprovarSolicitacao = strSim;
  }

  if ($("#PAR_AprovaPedidodeCompra").is(":checked")) {
    strAprovarPedido = strSim;
  }

  if ($("#PAR_AprovaContrato").is(":checked")) {
    strAprovarContrato = strSim;
  }

  if ($("#PAR_AnexoObrigatorio").is(":checked")) {
    strAnexoObrigatorio = strSim;
  }

  if ($("#PAR_SolicitacaoDataEntregaObrigatorio").is(":checked")) {
    strSolicitacaoDataEntregaObrigatorio = strSim;
  }

  if ($("#PAR_CotacoesCondicaoPagamentoObrigatoria").is(":checked")) {
    strCotacoesCondicaoPagamentoObrigatoria = strSim;
  }

  if ($("#PAR_ComprasApropriacoes").is(":checked")) {
    strApropriacoesCompras = strSim;
  }

  if ($("#PAR_UtilizarAlcadaCompras").is(":checked")) {
    strAlcadaCompras = strSim;
  }

  if ($("#PAR_BloquearValorOrcamento").is(":checked")) {
    strBloquearValorOrcamentos = strSim;
  }

  if ($("#PAR_ApropriacoesSolicitacoesAprovarEditar").is(":checked")) {
    strApropriacoesSolicitacoesAprovarEditar = strSim;
  }

  if ($("#PAR_ApropriacoesPedidosAprovarEditar").is(":checked")) {
    strApropriacoesPedidosAprovarEditar = strSim;
  }

  if ($("#PAR_ApropriacoesContratosAprovarEditar").is(":checked")) {
    strApropriacoesContratosAprovarEditar = strSim;
  }

  if ($("#PAR_TravaCondicaoPagamentoDocumentos").is(":checked")) {
    strTravaCondicaoPagamentoDocumentos = strSim;
  }

  if ($("#PAR_GerarPedidoLivreContrato").is(":checked")) {
    strGerarPedidoLivreContrato = strSim;
  }

  if ($("#PAR_AprovarCotacao").is(":checked")) {
    strAprovacaoCotacao = strSim;
  }

  if ($("#PAR_OrcamentoCompras").is(":checked")) {
    strOrcamento = strSim;
  }

  if ($("#PAR_ObservacaoCotacao").is(":checked")) {
    strObservacaoCotacao = strSim;
  }
  if ($("#PAR_PedidoEntregue").is(":checked")) {
    strPedidoEntregue = strSim;
  }

  $.ajax({
    url: $.trim($("#grupos_empresas_parametros_compras").val()),
    dataType: "json",
    cache: false,
    data: {
      PAR_AprovaSolicitacao: $.trim(strAprovarSolicitacao),
      PAR_AprovaPedidodeCompra: $.trim(strAprovarPedido),
      PAR_AprovaContrato: $.trim(strAprovarContrato),
      PAR_AnexoObrigatorio: $.trim(strAnexoObrigatorio),
      PAR_SolicitacaoDataEntregaObrigatorio: $.trim(
        strSolicitacaoDataEntregaObrigatorio
      ),
      PAR_CotacoesCondicaoPagamentoObrigatoria: $.trim(
        strCotacoesCondicaoPagamentoObrigatoria
      ),
      PAR_OrientacoesPedido: $.trim($("#PAR_OrientacoesPedido").val()),
      PAR_ComprasApropriacoes: strApropriacoesCompras,
      PAR_UtilizarAlcada: $.trim(strAlcadaCompras),
      PAR_BloquearValorOrcamento: $.trim(strBloquearValorOrcamentos),
      PAR_ApropriacoesSolicitacoesAprovarEditar: $.trim(
        strApropriacoesSolicitacoesAprovarEditar
      ),
      PAR_ApropriacoesPedidosAprovarEditar: $.trim(
        strApropriacoesPedidosAprovarEditar
      ),
      PAR_ApropriacoesContratosAprovarEditar: $.trim(
        strApropriacoesContratosAprovarEditar
      ),
      PAR_TravaCondicaoPagamentoDocumentos: $.trim(
        strTravaCondicaoPagamentoDocumentos
      ),
      PAR_GerarPedidoLivreContrato: $.trim(strGerarPedidoLivreContrato),
      PAR_AprovarCotacao: $.trim(strAprovacaoCotacao),
      PAR_Orcamento: $.trim(strOrcamento),
      PAR_ObservacaoCotacao: $.trim(strObservacaoCotacao),
      PAR_PedidoEntregue: $.trim(strPedidoEntregue),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvarCompras").html(strLabel);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $.notify(data.mensagem, "success");
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvarCompras").html(strLabel);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function impressaoCarteiraImpostoRenda() {
  // if ($.trim($("#ENT_ID").val()) == "") {
  //   $.notify("Cliente precisa ser informado.", "warn");
  //   return;
  // } else if ($.trim($("#UNI_ID").val()) == "") {
  //   $.notify("Unidade precisa ser informada.", "warn");
  //   return;
  // } else if ($.trim($("#ANO_ID").val()) == "") {
  //   $.notify("Ano precisa ser informado.", "warn");
  //   return;
  // } else {
    $("#frmFormulario").prop("target", "_blank");
    $("#frmFormulario").attr(
      "action",
      $.trim($("#carteiras_contratos_impressao_imposto_renda").val())
    );
    $("#frmFormulario").submit();
  // }
}

function marcarDesmarcarPedidosEstoques() {
  $(".btn-formulario").prop("disabled", true);
  preLoadingClose();

  var bolMarcado = true;
  $(".marcarItem").each(function () {
    if (this.checked) bolMarcado = false;
  });

  $.ajax({
    url: $.trim($("#pedidos_atualiza_estoque").val()),
    dataType: "json",
    cache: false,
    data: {
      PED_ID: $.trim($("#PED_ID").val()),
      PED_Marcado: bolMarcado,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      if (data.selecionar == strSim) {
        $(".marcarItem").prop("checked", true);
      } else {
        $(".marcarItem").prop("checked", false);
      }

      $.notify(data.mensagem, "success");
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarEntidadesAnexos() {
  $(".btn-formulario").prop("disabled", true);
  $("#consultar-anexos").html(strCarregando);
  preLoadingClose();

  $.ajax({
    url: $.trim($("#entidades_anexos_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      ENT_ID: $.trim($("#ENT_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        $("#consultar-anexos").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#consultar-anexos").html(data.strHtml);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#consultar-anexos").html("");
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function initEntidadesVendas() {
  $(document).ready(function () {
    preLoadingOpen();

    $("#btnAdicionarCadastrosAuxiliares").hide();

    $(".multiplos").multiselect(getOptions());
    $('a[data-toggle="tab"]').on("shown.bs.tab", function (e) {
      resetChosen();
    });

    if ($.trim($("#ENT_ImagemAnterior").val()) != "") {
      $(".pop").on("click", function () {
        $(".imagepreview").attr("src", $.trim($("#ENT_ImagemAnterior").val()));
        $("#modal-image").modal("show");
      });
    }

    $("#divCampos").hide();
    $("#SGP_CPFCNPJ").unmask();
    $("#dadosPessoaFisica").hide();
    $("#dadosPessoaJuridica").hide();
    $("#grp-cpfcnpj").hide();
    $("#grp-razao").hide();
    $("#grp-fantasia").hide();
    $("#li-aba-tributario").hide();

    $("#GRE_ID").change(function () {
      $("#CAX_ID").html("");
      $("#select-status-agentes").html(strCarregando);
      $("#btnAdicionarCadastrosAuxiliares").hide();

      if ($.trim(this.value) != "") {
        $("#btnAdicionarCadastrosAuxiliares").show();

        $.ajax({
          url: $.trim(
            $("#portal_vendas_cadastros_auxiliares_grupo_empresa_tipo").val()
          ),
          dataType: "json",
          cache: false,
          data: {
            GRE_ID: $.trim(this.value),
            TCX_ID: $.trim($("#TCX_ID").val()),
          },
          type: "POST",
        })
          .success(function (data) {
            $(".btn-formulario").prop("disabled", false);

            if (data.error != undefined) {
              $("#CAX_ID").html("");
              dialogAlert(strAtencao, data.error.msg, 6);
              return;
            }

            $("#select-status-agentes").html(data.strHtml);
            $("#CAX_Status_ID").selectpicker("refresh");

            var strHtml = "";
            if (data.arrDados != undefined) {
              strHtml += "<option value=''>" + strSelecione + "</option>";
              for (var a = 0; a < data.arrDados.length; a++) {
                strHtml +=
                  "<option value='" +
                  data.arrDados[a].CAX_ID +
                  "'>" +
                  data.arrDados[a].CAX_Descricao +
                  "</option>";
              }
            }

            $("#CAX_ID").html(strHtml);
          })
          .fail(function (data) {
            $("#CAX_ID").html("");
            $(".btn-formulario").prop("disabled", false);
            dialogAlert(strAtencao, data.responseText, 6);
            return;
          });
      } else {
        $("#select-status-agentes").html("");
        $("#CAX_Status_ID").selectpicker("refresh");
      }
    });

    $("#EMP_TipoPessoa").change(function () {
      $("#divCampos").hide();
      $("#SGP_CPFCNPJ").unmask();
      $("#dadosPessoaFisica").hide();
      $("#dadosPessoaJuridica").hide();
      $("#grp-cpfcnpj").hide();
      $("#grp-razao").hide();
      $("#grp-fantasia").hide();
      $("#li-aba-conjuge").hide();
      $("#ENC_CPF").mask("999.999.999-99");
      if (this.value == $("#hddFlagPessoaFisica").val()) {
        $("#divCampos").show();
        $("#lblCPFCNPJ").html(
          'CPF <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
        );
        $("#SGP_CPFCNPJ").mask("999.999.999-99");
        $("#SGP_CPFCNPJ").attr("placeholder", "Informe o CPF");
        $("#lbl-razao").html(
          'Nome <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
        );
        $("#lbl-fantasia").html(
          'Apelido <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
        );
        $("#ENT_RazaoSocial").attr("placeholder", "Informe o nome");
        $("#ENT_NomeFantasia").attr("placeholder", "Informe o apelido");
        $("#dadosPessoaJuridica").hide();
        $("#dadosPessoaFisica").show();
        $("#grp-cpfcnpj").show();
        $("#grp-razao").show();
        $("#grp-fantasia").show();

        exibirEntidadesAbaConjugeVendas();
      } else if (this.value == $("#hddFlagPessoaJuridica").val()) {
        $("#divCampos").show();
        $("#lblCPFCNPJ").html(
          'CNPJ <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
        );
        $("#SGP_CPFCNPJ").mask("99.999.999/9999-99");
        $("#SGP_CPFCNPJ").attr("placeholder", "Informe o CNPJ");
        $("#lbl-razao").html(
          'Razão Social <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
        );
        $("#lbl-fantasia").html(
          'Nome Fantasia <span data-toggle="tooltip" title="Campo obrigatório" style="color:#FF0000;cursor:pointer;">*</span>'
        );
        $("#ENT_RazaoSocial").attr("placeholder", "Informe a razão social");
        $("#ENT_NomeFantasia").attr("placeholder", "Informe o nome fantasia");
        $("#dadosPessoaFisica").hide();
        $("#dadosPessoaJuridica").show();
        $("#grp-cpfcnpj").show();
        $("#grp-razao").show();
        $("#grp-fantasia").show();
      } else {
        $(".chosen").trigger("chosen:updated");
      }

      resetChosen();
    });

    $("#TPE_ID").change(function () {
      exibirEntidadesAbaConjugeVendas();

      $("#li-aba-tributario").hide();
      $("select[name='TPE_ID[]'] option:selected").each(function () {
        if ($(this).val() == 2) {
          $("#li-aba-tributario").show();
          return;
        }
      });
    });

    $("#EMP_EstadoCivil").change(function () {
      if ($.trim(this.value) != "") {
        exibirEntidadesAbaConjugeVendas();
      }
    });

    if ($.trim($("#ENT_ID").val()) != "") {
      $(".chosen").trigger("chosen:updated");
      $("#btnAdicionarCadastrosAuxiliares").show();
    }

    $("#EMP_TipoPessoa, #TPE_ID").trigger("change");
    resetChosen();
    preLoadingClose();
  });

  resetChosen();
}

function exibirEntidadesAbaConjugeVendas() {
  $("#li-aba-conjuge").hide();

  $("#ENC_CPF, #ENC_Nome, #ENC_Sexo, #UF_IDConjuge").attr("required", false);

  var arrTiposEntidades = new Array();
  $("select[name='TPE_ID[]'] option:selected").each(function () {
    arrTiposEntidades.push($(this).val());
  });

  if (
    $.trim($("#EMP_TipoPessoa").val()) != "" &&
    $.trim($("#EMP_EstadoCivil").val()) != ""
  ) {
    $.ajax({
      url: $.trim($("#entidades_exibir_conjuge_vendas").val()),
      dataType: "json",
      cache: false,
      data: {
        EMP_TipoPessoa: $.trim($("#EMP_TipoPessoa").val()),
        TPE_ID: arrTiposEntidades,
        EMP_EstadoCivil: $.trim($("#EMP_EstadoCivil").val()),
      },
      type: "POST",
    }).success(function (data) {
      //alert(data); return;
      if (data.sucesso == "true") {
        $("#li-aba-conjuge").show();
        $("#ENC_CPF, #ENC_Nome, #ENC_Sexo, #UF_IDConjuge").attr(
          "required",
          true
        );
      }
    });
  }
}

function consultarEntidadesAnexosVendas() {
  $(".btn-formulario").prop("disabled", true);
  $("#consultar-anexos").html(strCarregando);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#portal_vendas_entidades_anexos_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      ENT_ID: $.trim($("#ENT_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        $("#consultar-anexos").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
      }

      $("#consultar-anexos").html(data.strHtml);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#consultar-anexos").html("");
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarEntidadesVendas() {
  if ($.trim($("#GRE_ID").val()) == "") {
    $.notify("Grupo de empresa precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#EMP_TipoPessoa").val()) == "") {
    $.notify("Tipo pessoa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#SGP_CPFCNPJ").val()) == "") {
    $.notify("CPF/CNPJ preicsa ser informado.", "warn");
    return;
  } else if ($.trim($("#ENT_RazaoSocial").val()) == "") {
    $.notify("Razão Social/Nome precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#ENT_NomeFantasia").val()) == "") {
    $.notify("Nome Fantasia/Apelido precisa ser informado.", "warn");
    return;
  } else {
    //Verifica os campos obrigatórios de acordo com o estado civil selecionado
    var validarEstadoCivil = verificaEstadoCivil($("#EMP_EstadoCivil").val());

    if (validarEstadoCivil == true && $.trim($("#ENC_CPF").val()) == "") {
      $.notify("CPF do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    } else if (
      validarEstadoCivil == true &&
      $.trim($("#ENC_Nome").val()) == ""
    ) {
      $.notify("Nome do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    } else if (
      validarEstadoCivil == true &&
      $.trim($("#ENC_Sexo").val()) == ""
    ) {
      $.notify("Sexo do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    } else if (
      validarEstadoCivil == true &&
      $.trim($("#UF_IDConjuge").val()) == ""
    ) {
      $.notify("Estado do conjuge precisa ser informado.", "warn");
      $('.nav-tabs a[href="#tab-conjuge"]').tab("show");
      return;
    } else if (
      $.trim($("#CAX_ID").val()) == "" &&
      $.trim($("#AEN_Descricao").val()) == "" &&
      $.trim($("#AEN_Arquivo").val()) == ""
    ) {
    } else if (
      $.trim($("#CAX_ID").val()) == "" ||
      $.trim($("#AEN_Descricao").val()) == "" ||
      $.trim($("#AEN_Arquivo").val()) == ""
    ) {
      $.notify("Todos os campos da aba Anexos devem ser preechidos.", "warn");
      $('.nav-tabs a[href="#tab-anexos"]').tab("show");
      return;
    }

    preLoadingOpen();
    $(".btn-formulario").prop("disabled", true);

    var arrDados = new FormData();

    //Cadastro
    var arrTipoEntidades = new Array();
    $("select[name='TPE_ID[]'] option:selected").each(function () {
      arrTipoEntidades.push($(this).val());
    });

    for (var i = 0; i < arrTipoEntidades.length; i++) {
      arrDados.append("TPE_ID[]", arrTipoEntidades[i]);
    }

    arrDados.append("GRE_ID", $.trim($("#GRE_ID").val()));
    arrDados.append("ENT_ID", $.trim($("#ENT_ID").val()));
    arrDados.append("ENT_TipoPessoa", $.trim($("#EMP_TipoPessoa").val()));
    arrDados.append("ENT_CPFCNPJ", $.trim($("#SGP_CPFCNPJ").val()));
    arrDados.append("ENT_RazaoSocial", $.trim($("#ENT_RazaoSocial").val()));
    arrDados.append("ENT_NomeFantasia", $.trim($("#ENT_NomeFantasia").val()));
    arrDados.append("ENT_EstadoCivil", $.trim($("#EMP_EstadoCivil").val()));
    arrDados.append("ENT_Sexo", $.trim($("#EMP_Sexo").val()));
    arrDados.append("ENT_RG", $.trim($("#ENT_RG").val()));
    arrDados.append(
      "ENT_DataNascimento",
      $.trim($("#ENT_DataNascimento").val())
    );
    arrDados.append(
      "ENT_RG_OrgaoEmissor",
      $.trim($("#ENT_RG_OrgaoEmissor").val())
    );
    arrDados.append(
      "ENT_RG_OrgaoEmissor_UF",
      $.trim($("#ENT_RG_OrgaoEmissor_UF").val())
    );
    arrDados.append(
      "ENT_RG_DataExpedicao",
      $.trim($("#ENT_RG_DataExpedicao").val())
    );
    arrDados.append("ENT_Naturalidade", $.trim($("#ENT_Naturalidade").val()));
    arrDados.append("ENT_Nacionalidade", $.trim($("#ENT_Nacionalidade").val()));
    arrDados.append("ENT_CNAE", $.trim($("#ENT_CNAE").val()));
    arrDados.append(
      "ENT_InscricaoMunicipal",
      $.trim($("#ENT_InscricaoMunicipal").val())
    );
    arrDados.append(
      "ENT_InscricaoEstadual",
      $.trim($("#ENT_InscricaoEstadual").val())
    );
    arrDados.append("ENT_CEP", $.trim($("#TER_CEP").val()));
    arrDados.append("ENT_Endereco", $.trim($("#TER_Endereco").val()));
    arrDados.append("ENT_Numero", $.trim($("#TER_Numero").val()));
    arrDados.append("ENT_Complemento", $.trim($("#TER_Complemento").val()));
    arrDados.append("ENT_Bairro", $.trim($("#TER_Bairro").val()));
    arrDados.append("ENT_Cidade", $.trim($("#TER_Cidade").val()));
    arrDados.append("UF_ID", $.trim($("#UF_ID").val()));
    arrDados.append("ENT_CodigoDARF", $.trim($("#ENT_CodigoDARF").val()));
    arrDados.append("CAX_Status_ID", $.trim($("#CAX_Status_ID").val()));

    //Contatos
    arrDados.append("ENT_Contato", $.trim($("#ENT_Contato").val()));
    arrDados.append("ENT_Email", $.trim($("#ENT_Email").val()));
    arrDados.append("ENT_Telefone", $.trim($("#ENT_Telefone").val()));
    arrDados.append("ENT_Celular", $.trim($("#ENT_Celular").val()));
    arrDados.append("ENT_Fax", $.trim($("#ENT_Fax").val()));

    //Observações
    arrDados.append("ENT_Observacao", $.trim($("#ENT_Observacao").val()));

    //Conjuge
    arrDados.append("ENC_CPF", $.trim($("#ENC_CPF").val()));
    arrDados.append("ENC_Nome", $.trim($("#ENC_Nome").val()));
    arrDados.append("ENC_Sexo", $.trim($("#ENC_Sexo").val()));
    arrDados.append("ENC_RG", $.trim($("#ENC_RG").val()));
    arrDados.append(
      "ENC_DataNascimento",
      $.trim($("#ENC_DataNascimento").val())
    );
    arrDados.append(
      "ENC_RG_OrgaoEmissor",
      $.trim($("#ENC_RG_OrgaoEmissor").val())
    );
    arrDados.append("UF_OrgaoEmissor", $.trim($("#UF_OrgaoEmissor").val()));
    arrDados.append("ENC_Naturalidade", $.trim($("#ENC_Naturalidade").val()));
    arrDados.append("ENC_Nacionalidade", $.trim($("#ENC_Nacionalidade").val()));
    arrDados.append("ENC_Email", $.trim($("#ENC_Email").val()));
    arrDados.append("ENC_Telefone", $.trim($("#ENC_Telefone").val()));
    arrDados.append("ENC_Celular", $.trim($("#ENC_Celular").val()));
    arrDados.append("ENC_CEP", $.trim($("#ENC_CEP").val()));
    arrDados.append("ENC_Endereco", $.trim($("#ENC_Endereco").val()));
    arrDados.append("ENC_Numero", $.trim($("#ENC_Numero").val()));
    arrDados.append("ENC_Complemento", $.trim($("#ENC_Complemento").val()));
    arrDados.append("ENC_Bairro", $.trim($("#ENC_Bairro").val()));
    arrDados.append("ENC_Cidade", $.trim($("#ENC_Cidade").val()));
    arrDados.append("UF_IDConjuge", $.trim($("#UF_IDConjuge").val()));

    //Anexos
    if ($.trim($("#CAX_ID").val()) != "") {
      arrDados.append("CAX_ID", $.trim($("#CAX_ID").val()));
    }

    if ($.trim($("#AEN_Descricao").val()) != "") {
      arrDados.append("AEN_Descricao", $.trim($("#AEN_Descricao").val()));
    }

    if ($("#AEN_Arquivo").prop("files")[0] != undefined) {
      arrDados.append("AEN_Arquivo", $("#AEN_Arquivo").prop("files")[0]);
    }

    $.ajax({
      url: $.trim($("#portal_vendas_entidades_salvar").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "POST",
    })
      .success(function (data) {
        preLoadingClose();
        $(".btn-formulario").prop("disabled", false);

        if (data.error != undefined) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        if (data.redir != undefined) {
          setTimeout(function () {
            redir(data.redir);
          }, 1000);
        }
      })
      .fail(function (data) {
        preLoadingClose();
        $(".btn-formulario").prop("disabled", false);
        dialogAlert(strAtencao, data.responseText, 6);
        return;
      });
  }
}

function calcularComprasSolicitacoesApropriacoesPercentual(intCodigo, valor) {
  $(".btn-formulario").prop("disabled", true);

  var arrValores = new Array();
  $("input[type='checkbox'][name='chkSelecionar[]']:checked").each(function () {
    arrValores.push($(this).val());
  });

  $.ajax({
    url: $.trim($("#solicitacoes_calcular_apropriacoes_percentual").val()),
    dataType: "json",
    cache: false,
    data: {
      SOL_ID: $.trim(intCodigo),
      SOA_Percentual: $.trim(valor),
      arrValores: arrValores,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      if (data.mensagem != undefined) {
        $.notify(data.mensagem, "warn");
      }

      if (data.arrValoresOK != undefined) {
        for (var key in data.arrValoresOK) {
          var valor = data.arrValoresOK[key].split("|");
          $("#smlItem" + key).removeClass("label-danger");
          $("#smlItem" + key).removeClass("label-primary");
          $("#spnPercentualInsumo" + key).html(valor[0]);
          $("#smlItem" + key).addClass(valor[1]);
        }
      }
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function getItemOrcamentoPorOrcamento(intCodigo) {
  $("#OCI_ID2").html("");
  $("#OCI_ID2").append("<option value=''>" + strSelecione + "</option>");

  if ($.trim(intCodigo) != "") {
    $.post(
      $.trim($("#hddApropriacoesDados").val()),
      { ORC_ID: intCodigo },
      function (data) {
        //alert(data);
        if (data.sucesso == "true") {
          if (data.arrDados.length > 0) {
            for (var i = 0; i < data.arrDados.length; i++) {
              $("#OCI_ID2").append(
                "<option value='" +
                data.arrDados[i].OCI_ID +
                "'>" +
                data.arrDados[i].OCI_Codigo +
                " - " +
                data.arrDados[i].OCI_Descricao +
                "</option>"
              );
            }
          }

          $("#OCI_ID2").trigger("chosen:updated");
        } else {
          $.notify(data.error.msg, "warn");
        }
      },
      "json"
    );
  }
}

function salvarItemApropriacaoMultiplos() {
  var arrValores = new Array();

  $("input[type='checkbox'][name='chkSelecionar[]']:checked").each(function () {
    arrValores.push($(this).val());
  });

  if (arrValores.length > 0){

    $(".btn-formulario").prop("disabled", true);

    $.ajax({
      url: $.trim($("#solicitacoes_salvar_apropriacoes_multiplos").val()),
      dataType: "json",
      cache: false,
      data: {
        SOL_ID: $.trim($("#SOL_ID").val()),
        SIT_ID: arrValores,
        CEN_ID: $.trim($("#CEN_ID").val()),
        ORC_ID: $.trim($("#ORC_ID2").val()),
        OCI_ID: $.trim($("#OCI_ID2").val()),
        PLF_Conta: $.trim($("#PLF_Conta2").val()),
        SOA_Percentual: $.trim($("#SOA_Percentual2").val()),
        SIT_DataPrevisaoEntrega: $.trim($("#SIT_DataPrevisaoEntrega").val()),
      },
      type: "POST",
    }).success(function (data) {
        $(".btn-formulario").prop("disabled", false);
  
        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }
  
        $("#CEN_ID, #SIT_DataPrevisaoEntrega, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SOA_Percentual2").val("");
        $("#OCI_ID2").html("<option value=''>" + strSelecione + "</option>");
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2").trigger("chosen:updated");
  
        if (data.arrValoresOK != undefined) {
          for (var key in data.arrValoresOK) {
            var valor = data.arrValoresOK[key].split("|");
            $("#smlItem" + key).removeClass("label-danger");
            $("#smlItem" + key).removeClass("label-primary");
            $("#spnPercentualInsumo" + key).html(valor[0]);
            $("#smlItem" + key).addClass(valor[1]);
          }
        }
  
        $.notify(data.mensagem, "success");
        fecharModal();
        consultarItensSolicitacoes($.trim($("#SOL_ID").val()), arrValores);
  
      }).fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }else{
    $.notify("Selecione no minímo 1 (UMA) opção.", "danger");
  }
}

function salvarComprasAlcadasUsuarios(intUsuario) {
  var arrAlcadas = new Array();

  $("select[name='SGP_Alcadas" + intUsuario + "[]'] option:selected").each(
    function () {
      arrAlcadas.push($(this).val());
    }
  );

  $.ajax({
    url: $.trim($("#utilizar_alcada_modulo_rota_perfil_salvar").val()),
    dataType: "json",
    cache: false,
    data: {
      USU_ID: $.trim(intUsuario),
      UAL_ID: arrAlcadas,
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $.notify(data.mensagem, "success");
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarFinanceiroAlcadasUsuarios(intUsuario) {
  var arrAlcadas = new Array();

  $("select[name='SGP_Alcadas" + intUsuario + "[]'] option:selected").each(
    function () {
      arrAlcadas.push($(this).val());
    }
  );

  $.ajax({
    url: $.trim($("#perfis_salvar_alcadas_financeiro").val()),
    dataType: "json",
    cache: false,
    data: {
      USU_ID: $.trim(intUsuario),
      UAL_ID: arrAlcadas,
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $.notify(data.mensagem, "success");
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function consultarPlanejamentoVisualizarComparativo() {
  $("#btnExportar").hide();
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ORC_ID").val()) == "") {
    $.notify("Orçamento pessoa precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#txtDataInicial").val()) == "") {
    $.notify("Data inicial precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#txtDataFinal").val()) == "") {
    $.notify("Data final precisa ser informada.", "warn");
    return;
  } else if (
    $.trim($("#txtDataInicial").val()) > $.trim($("#txtDataFinal").val())
  ) {
    $.notify("Data inicial deve ser menor que a data final.", "warn");
    return;
  } else {
    $("#btnFiltrar").prop("disabled", true);
    var strLabel = $("#btnFiltrar").html();
    $("#btnFiltrar, #consultar-dados").html(strCarregando);

    preLoadingOpen();

    $.ajax({
      url: $.trim($("#relatorios_planejamento_visualizar_comparativo").val()),
      dataType: "json",
      cache: false,
      data: {
        EMP_ID: $.trim($("#EMP_ID").val()),
        ORC_ID: $.trim($("#ORC_ID").val()),
        SGP_DataInicial: $.trim($("#txtDataInicial").val()),
        SGP_DataFinal: $.trim($("#txtDataFinal").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnFiltrar").prop("disabled", false);
        $("#btnFiltrar").html(strLabel);

        if (data.error) {
          $("#consultar-dados").html("");
          preLoadingClose();
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $("#consultar-dados").html(data.strHtml);
        $("#btnExportar").show();

        /*for (var k in data.arrItensComprometidosValores){
        if (data.arrItensComprometidosValores.hasOwnProperty(k)) {
          $('#'+k).html(data.arrItensComprometidosValores[k]);
        }
      }*/

        preLoadingClose();
      })
      .fail(function (data) {
        $("#btnFiltrar").prop("disabled", false);
        $("#btnFiltrar").html(strLabel);
        $("#consultar-dados").html("");
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function salvarComprasAdiantamentos() {
  if ($.trim($("#EMP_ID").val()) == "") {
    $.notify("Empresa precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ENT_ID").val()) == "") {
    $.notify("Fornecedor pessoa precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#CAX_ID").val()) == "") {
    $.notify("Tipo precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#ADI_NumeroDocumento").val()) == "") {
    $.notify("Número do documento precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#ADI_DataEmissao").val()) == "") {
    $.notify("Data de emissão precisa ser informada.", "warn");
    return;
  } else if (
    $.trim($("#ADI_Valor").val()) == "" ||
    $.trim($("#ADI_Valor").val()) == "0,00"
  ) {
    $.notify("Valor precisa ser informado.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnSalvar").html();
    $("#btnSalvar").html(strCarregando);
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#compras_adiantamentos_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        ADI_ID: $.trim($("#ADI_ID").val()),
        EMP_ID: $.trim($("#EMP_ID").val()),
        ENT_ID: $.trim($("#ENT_ID").val()),
        //CPG_ID: $.trim($('#CPG_ID').val()),
        CAX_ID: $.trim($("#CAX_ID").val()),
        ADI_NumeroDocumento: $.trim($("#ADI_NumeroDocumento").val()),
        ADI_DataEmissao: $.trim($("#ADI_DataEmissao").val()),
        ADI_Valor: $.trim($("#ADI_Valor").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvar").html(strLabel);
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        if ($.trim($("#ADI_ID").val()) == "") {
          setTimeout(function () {
            redir(data.redir);
          }, 1500);
        }
      })
      .fail(function (data) {
        $("#btnSalvar").html(strLabel);
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function salvarComprasAdiantamentosAnexos() {
  if ($.trim($("#ADA_Anexo").val()) == "") {
    $.notify("Anexo precisa ser informado.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnSalvarAnexo").html();
    $("#btnSalvarAnexo, #consultar-anexos").html(strCarregando);
    preLoadingOpen();

    var arrDados = new FormData();

    arrDados.append("ADI_ID", $("#ADI_ID").val());
    arrDados.append("ADA_Anexo", $("#ADA_Anexo").prop("files")[0]);

    $.ajax({
      url: $.trim($("#compras_adiantamentos_anexos_salvar").val()),
      dataType: "json",
      cache: false,
      contentType: false,
      processData: false,
      data: arrDados,
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarAnexo").html(strLabel);
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();

        if (data.error) {
          $("#consultar-anexos").html("");
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");
        consultarComprasAdiantamentosAnexos();
      })
      .fail(function (data) {
        $("#btnSalvarAnexo").html(strLabel);
        $("#consultar-anexos").html("");
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarComprasAdiantamentosAnexos() {
  $.ajax({
    url:
      $.trim($("#compras_adiantamentos_anexos_consultar").val()) +
      "/" +
      $.trim($("#ADI_ID").val()),
    dataType: "json",
    cache: false,
    data: {
      SGP_Dados: true,
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        $("#consultar-anexos").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#consultar-anexos").html(data.strHtml);
    })
    .fail(function (data) {
      $("#consultar-anexos").html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarComprasAdiantamentosGerarTitulo() {
  if ($.trim($("#CPP_FormaPagamento").val()) == "") {
    $.notify("Forma de pagamento precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CPP_DataVencimento").val()) == "") {
    $.notify("Data de vencimento pessoa precisa ser informada.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnSalvarDialog").html();
    $("#btnSalvarDialog").html(strCarregando);
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#compras_adiantamentos_gerar_titulo_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        ADI_ID: $.trim($("#hddCodigoAdiantamento").val()),
        CPP_FormaPagamento: $.trim($("#CPP_FormaPagamento").val()),
        CPP_DataVencimento: $.trim($("#CPP_DataVencimento").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarDialog").html(strLabel);
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        setTimeout(function () {
          redir(data.redir);
        }, 1500);
      })
      .fail(function (data) {
        $("#btnSalvarDialog").html(strLabel);
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function getPlanejamentoItensOrcamentos(intCodigo) {
  $(".btn-formulario").prop("disabled", true);

  $.ajax({
    url: $.trim($("#orcamentos_itens").val()) + "/" + $.trim(intCodigo),
    dataType: "json",
    cache: false,
    data: {
      SGP_Informacoes: true,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
      return;
    });
}

function salvarComprasContratosAditivos() {
  if ($.trim($("#CTA_DataEmissao").val()) == "") {
    $.notify("Data de emissão precisa precisa ser informada.", "warn");
    return;
  }else{
    $(".btn-formulario").prop("disabled", true);

    var strLabel = $("#btnSalvarAditivos").html();
    $("#btnSalvarAditivos, #consultar-aditivos").html(strCarregando);
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#compras_contratos_aditivos_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        CON_ID: $.trim($("#CON_ID").val()),
        COP_ID: $.trim($("#COP_IDAditivo").val()),
        CTA_DataEmissao: $.trim($("#CTA_DataEmissao").val()),
        CTA_Observacoes: $.trim($("#CTA_Observacoes").val())
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvarAditivos").html(strLabel);
        preLoadingClose();

        if (data.error) {
          $("#consultar-aditivos").html("");
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        $("#COP_IDAditivo").val("");

        consultarComprasContratosAditivos();
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvarAditivos").html(strLabel);
        $("#consultar-aditivos").html("");
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function consultarComprasContratosAditivos(){
  $("#consultar-aditivos").html(strCarregando);

  setTimeout(function () {
    $('#COP_IDAditivo').chosen('destroy');
    $('#COP_IDAditivo').prop("selectedindex", -1);   
    $('#COP_IDAditivo').chosen();  
  }, 500);

  $.ajax({
    url: $.trim($("#compras_contratos_aditivos_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      CON_ID: $.trim($("#CON_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        $("#consultar-aditivos").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#consultar-aditivos").html(data.strHtml);
    })
    .fail(function (data) {
      $("#consultar-aditivos").html("");
      dialogAlert(strAtencao, data.responseText, 6);
    });


}

function consultarComprasContratosAditivosInsumos(intCodigo) {
  $(".btn-formulario").prop("disabled", true);
  $("#consultar-itens").html(strCarregando);

  $.ajax({
    url: $.trim($("#compras_contratos_aditivos_consultar_itens").val()),
    dataType: "json",
    cache: false,
    data: {
      CON_ID: $.trim($("#CON_ID").val()),
      CTA_ID: $.trim(intCodigo),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        $("#consultar-itens").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#consultar-itens").html(data.strHtml);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#consultar-itens").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarComprasContratosAditivosItens() {
  if ($.trim($("#INS_ID").val()) == "") {
    $.notify("Insumo precisa precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#UNM_ID").val()) == "") {
    $.notify("Unidade de medida precisa ser informada.", "warn");
    return;
  } else if ($.trim($("#CAI_Quantidade").val()) == "" || $.trim($("#CAI_Quantidade").val()) == "0"){
    $.notify("Quantidade precisa ser informada ou ser maior que zero.", "warn");
    return;
  }else if ($.trim($("#CAI_ValorUnitario").val()) == "" || $.trim($("#CAI_ValorUnitario").val()) == "0"){
    $.notify("Valor unitário precisa ser informado ou ser maior que zero.", "warn");
    return;
  } else {
    $("#btnSalvarItem").prop("disabled", true);

    var strLabel = $("#btnSalvarItem").html();
    $("#btnSalvarItem, #consultar-itens").html(strCarregando);
    preLoadingOpen();

    $.ajax({
      url: $.trim($("#compras_contratos_aditivos_salvar_itens").val()),
      dataType: "json",
      cache: false,
      data: {
        CTA_ID: $.trim($("#intAditivo").val()),
        CON_ID: $.trim($("#CON_ID").val()),
        INS_ID: $.trim($("#INS_ID").val()),
        UNM_ID: $.trim($("#UNM_ID").val()),
        CAI_Quantidade: $.trim($("#CAI_Quantidade").val()),
        CAI_ValorUnitario: $.trim($("#CAI_ValorUnitario").val()),
        CAI_ValorTotal: $.trim($("#CAI_ValorTotal").val()),
        CAI_DataPrevisao: $.trim($("#CAI_DataPrevisao").val()),
        CAI_Detalhes: $.trim($("#CAI_Detalhes").val())
      },
      type: "POST",
    }).success(function (data){
        $("#btnSalvarItem").prop("disabled", false);
        $("#btnSalvarItem").html(strLabel);
        preLoadingClose();

        if (data.error) {
          $("#consultar-itens").html("");
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        $("#CAI_DataPrevisao, #INS_ID, #INS_Codigo, #INS_Pesquisar, #UNM_ID, #CAI_Quantidade, #CAI_ValorUnitario, #CAI_ValorTotal, #CAI_Detalhes").val("");
        $("#UNM_ID").trigger("chosen:updated");
        
        consultarComprasContratosAditivosInsumos(data.intCodigo);
        consultarComprasContratosAditivos();
        consultarItensContratos($.trim($("#CON_ID2").val()));

      }).fail(function (data){

        $("#btnSalvarItem").prop("disabled", false);
        $("#btnSalvarItem").html(strLabel);
        $("#consultar-itens").html("");
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function calcularComprasContratosAditivosValores() {
  $(".btn-formulario").prop("disabled", true);

  if (
    parseFloat($("#CAI_Quantidade").val()) > 0 &&
    parseFloat($("#CAI_ValorUnitario").val()) > 0
  ) {
    $.ajax({
      url: $.trim($("#compras_contratos_aditivos_calcular_itens").val()),
      dataType: "json",
      cache: false,
      data: {
        CAI_Quantidade: $.trim($("#CAI_Quantidade").val()),
        CAI_ValorUnitario: $.trim($("#CAI_ValorUnitario").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);

        if (data.error) {
          $("#CAI_ValorTotal").val("");
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $("#CAI_ValorTotal").val(data.douValor);
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#CAI_ValorTotal").val("");

        dialogAlert(strAtencao, data.responseText, 6);
      });
  } else {
    $(".btn-formulario").prop("disabled", false);
    $("#CAI_ValorTotal").val("");
  }
}

function consultarComprasContratosApropriacoes(intCodigo) {
  $(".btn-formulario").prop("disabled", true);
  $("#consultar-apropriacoes").html(strCarregando);

  $.ajax({
    url: $.trim($("#compras_contratos_aditivos_apropriacoes_consultar").val()),
    dataType: "json",
    cache: false,
    data: {
      CAI_ID: $.trim(intCodigo),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        $("#consultar-apropriacoes").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#SGP_Percentual").val(data.douValor);
      $("#SGP_Percentual").trigger("chosen:updated");
      $("#consultar-apropriacoes").html(data.strHtml);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#consultar-apropriacoes").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarComprasContratosAditivosApropriacoes() {
  if ($.trim($("#CEN_ID").val()) == "") {
    $.notify("Centro de custo precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#PLF_Conta2").val()) == "") {
    $.notify("Plano de conta financeiro precisa ser informado.", "warn");
    return;
  } else if (
    $.trim($("#SGP_Percentual").val()) == "" ||
    $.trim($("#SGP_Percentual").val()) == "0"
  ) {
    $.notify("Percentual precisa ser informado ou ser maior que zero.", "warn");
    return;
  } else {
    $(".btn-formulario").prop("disabled", true);

    var strLabel = $("#btnSalvarItemApropriacao").html();
    $("#consultar-apropriacoes").html(strCarregando);
    $("#btnSalvarItemApropriacao").html(strCarregandoIcone);

    $.ajax({
      url: $.trim($("#compras_contratos_aditivos_apropriacoes_salvar").val()),
      dataType: "json",
      cache: false,
      data: {
        CON_ID: $.trim($("#CON_ID").val()),
        CAI_ID: $.trim($("#hddCodigoAditivo").val()),
        CEN_ID: $.trim($("#CEN_ID").val()),
        ORC_ID: $.trim($("#ORC_ID2").val()),
        OCI_ID: $.trim($("#OCI_ID2").val()),
        PLF_Conta: $.trim($("#PLF_Conta2").val()),
        CAA_Percentual: $.trim($("#SGP_Percentual").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvarItemApropriacao").html(strLabel);

        if (data.error) {
          $("#consultar-apropriacoes").html("");
          dialogAlert(strAtencao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");

        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SGP_Percentual").val("");
        $("#CEN_ID, #ORC_ID2, #OCI_ID2, #PLF_Conta2, #SGP_Percentual").trigger(
          "chosen:updated"
        );
        consultarComprasContratosApropriacoes(data.intCodigo);
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        $("#btnSalvarItemApropriacao").html(strLabel);
        $("#consultar-apropriacoes").html("");

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function filtrarInsumosNovo2(
  callback,
  intCodigo,
  intInsumo,
  cssBtn,
  intLinha,
  autoCarregar
) {
  $(".btn-formulario").prop("disabled", true);
  var strLabel = $("#btnFiltrarInsumos").html();
  $("#btnFiltrarInsumos").html(strCarregandoIcone);

  $.ajax({
    url: $.trim($("#insumos_filtrar_novo").val()),
    dataType: "json",
    cache: false,
    data: {
      COM_ID: $.trim($("#COM_ID").val()),
      COI_ID: $.trim(intCodigo),
      INS_ID: $.trim(intInsumo),
      SGP_Callback: $.trim(callback),
      SGP_CssBtn: $.trim(cssBtn),
      SGP_Linha: $.trim(intLinha),
      SGP_Carregar: autoCarregar,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnFiltrarInsumos").html(strLabel);

      if (data.error) {
        $("#consultar-apropriacoes").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      dialogAlert2(
        data.strTitulo,
        data.strHtml,
        data.arrValores.dialog,
        "modalFiltrarNovoInsumos"
      );

      setTimeout(function () {
        $("#SGP_PesquisarFiltro").keyup(function (e) {
          enterPesquisarInsumosNovo(e, data.arrValores);
        });

        if (data.autoCarregar == true || data.arrValores.carregar == "true") {
          consultarInsumosNovo();
        }

        /*$(".clicarLinhaInsumosSelecionar").click(function(e){			

        $('#'+data.arrValores.html).html(strCarregando);
        var strCodigo = atob(data.arrValores.codigo);

        if (arrFiltros.selecionar != null){
          eval(arrFiltros.selecionar);
        }

        var arrSplit = $(this).attr('alt').split("|");

        $('#'+data.arrValores.html).html(arrSplit[1]+" - "+arrSplit[2]);
        $('#linkAdicionarInsumos'+strCodigo).removeClass("label-danger").addClass("label-success");
        $('#INS_Insumo_ID_'+strCodigo).val(arrSplit[0]);
        $('#modalFiltrarNovoInsumos').modal('hide');
      });*/

        $("#SGP_PesquisarFiltro").focus();
      }, 1000);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnFiltrarInsumos").html(strLabel);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function filtrarInsumosNovo(arrValores) {
  $("#btnFiltrarInsumos").prop("disabled", true);
  var strLabel = $("#btnFiltrarInsumos").html();
  $("#btnFiltrarInsumos").html(strCarregandoIcone);

  if (arrValores.codigo != null) {
    $("#linkAdicionarInsumos" + atob(arrValores.codigo))
      .removeClass("label-success")
      .addClass("label-danger");
    $("#INS_Insumo_ID_" + atob(arrValores.codigo)).val("");
    $("#spnInsumoSelecionado" + atob(arrValores.codigo)).html(
      $.trim($("#hddSelecione").val())
    );
  }

  $.ajax({
    url: $.trim($("#insumos_filtrar_novo").val()),
    dataType: "json",
    cache: false,
    data: {
      arrValores,
    },
    /*
    data: {
      COM_ID: $.trim($('#COM_ID').val()),
      COI_ID: $.trim(intCodigo),
      INS_ID: $.trim(intInsumo),
      SGP_Callback: $.trim(callback),
      SGP_CssBtn: $.trim(cssBtn),
      SGP_Linha: $.trim(intLinha),
      SGP_Carregar: autoCarregar,
      //SGP_Valores: $.trim($('#SGP_Valores').val()),			
    },
    */
    type: "POST",
  })
    .success(function (data) {
      $("#btnFiltrarInsumos").prop("disabled", false);
      $("#btnFiltrarInsumos").html(strLabel);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      dialogAlert2(
        data.strTitulo,
        data.strHtml,
        data.arrValores.dialog,
        "modalFiltrarNovoInsumos"
      );

      setTimeout(function () {
        $("#SGP_PesquisarFiltro").focus();
        if (data.arrValores.carregar == "true") {
          consultarInsumosNovo(data.arrValores);
        }

        $("#SGP_PesquisarFiltro").keyup(function (e) {
          enterPesquisarInsumosNovo(e, data.arrValores);
        });
      }, 1000);
    })
    .fail(function (data) {
      $("#btnFiltrarInsumos").prop("disabled", false);
      $("#btnFiltrarInsumos").html(strLabel);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function enterPesquisarInsumosNovo(e, arrValores = null) {
  if (e.keyCode == 13) {
    consultarInsumosNovo(arrValores);
  }
}

function consultarInsumosNovo(arrFiltros = null) {
  $(".btn-formulario").prop("disabled", true);
  $("#divPesquisarInsumos").html(strCarregando);

  arrFiltros.SGP_Pesquisar = $.trim($("#SGP_PesquisarFiltro").val());

  $.ajax({
    url: $.trim($("#insumos_consultar_novo").val()),
    dataType: "json",
    cache: false,
    data: arrFiltros,
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        $("#divPesquisarInsumos").html("");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#divPesquisarInsumos").html(data.strHtml);

      setTimeout(function () {
        $("#paginationDialog").html(data.pagination);
        $("#paginationDialog").on("click", "a", function (e) {
          e.preventDefault();
          var pageno = $(this).attr("data-ci-pagination-page");
          loadPagination(
            data.url,
            pageno,
            data.arrFiltros,
            "paginationDialog",
            "divPesquisarInsumos",
            $.trim($("#SGP_CssBtn").val())
          );
        });

        $(".clicarLinhaInsumosSelecionar").click(function (e) {
          $("#" + arrFiltros.html).html(strCarregando);
          var strCodigo = atob(arrFiltros.codigo);

          if (arrFiltros.selecionar != null) {
            eval(arrFiltros.selecionar);
          }

          var arrSplit = $(this).attr("alt").split("|");

          $("#" + arrFiltros.html).html(arrSplit[1] + " - " + arrSplit[2]);
          $("#linkAdicionarInsumos" + strCodigo)
            .removeClass("label-danger")
            .addClass("label-success");
          $("#INS_Insumo_ID_" + strCodigo).val(arrSplit[0]);
          $(
            "#CRI_Detalhes" +
            strCodigo +
            ", #CRI_Quantidade" +
            strCodigo +
            ", #CRI_ValorUnitario" +
            strCodigo
          ).prop("disabled", false);
          $("#modalFiltrarNovoInsumos").modal("hide");
        });
      }, 500);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#divPesquisarInsumos").html("");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function atualizarPlanoFinanceiroContadores() {
  $(".btn-filtro").prop("disabled", true);

  var strLabel = $("#btnSalvarDialog").html();
  $("#btnSalvarDialog").html(strCarregando);

  $.ajax({
    url: $.trim($("#contadores_planos_financeiros_salvar").val()),
    dataType: "json",
    cache: false,
    data: {
      PLF_Conta: $.trim($("#intCodigo").val()),
      PLF_ContabilProvisaoCredito: $.trim(
        $("#PLF_ContabilProvisaoCredito").val()
      ),
      PLF_ContabilProvisaoDebito: $.trim(
        $("#PLF_ContabilProvisaoDebito").val()
      ),
      PLF_ContabilRealizadoCredito: $.trim(
        $("#PLF_ContabilRealizadoCredito").val()
      ),
      PLF_ContabilRealizadoDebito: $.trim(
        $("#PLF_ContabilRealizadoDebito").val()
      ),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#btnSalvarDialog").html(strLabel);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $(".modal").modal("hide");
      $.notify(data.mensagem, "success");

      setTimeout(function () {
        redir("", "parent");
      }, 1500);
    })
    .fail(function (data) {
      $(".btn-filtro").prop("disabled", false);
      $("#btnSalvarDialog").html(strLabel);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function adicionarCondicaoPagamentoRapido() {
  $(".btn-formulario, .btn-pesquisar, #btnAdicionarCondicaoRapido").prop(
    "disabled",
    true
  );

  var strLabel = $("#btnAdicionarCondicaoRapido").html();
  $("#btnAdicionarCondicaoRapido").html(strCarregandoIcone);

  $.ajax({
    url: $.trim($("#condicoes_pagamentos_novo_rapido").val()),
    dataType: "json",
    cache: false,
    data: {
      SGP_Valor: true,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario, .btn-pesquisar, #btnAdicionarCondicaoRapido").prop(
        "disabled",
        false
      );
      $("#btnAdicionarCondicaoRapido").html(strLabel);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      dialogAlert(data.strTitulo, data.strHtml, 3);

      setTimeout(function () {
        $("#COP_QuantidadeParcelasRapido").on("keyup", function (event) {
          $("#btnAdicionarRapido").prop("disabled", true);
          $("#divExibirCondicoes").html(strCarregando);

          if ($.trim($(this).val()) > 0) {
            $.ajax({
              url: $.trim($("#condicoes_pagamentos_parcelas").val()),
              dataType: "json",
              cache: false,
              data: {
                COP_QuantidadeParcelas: $.trim($(this).val()),
              },
              type: "POST",
            })
              .success(function (data) {
                //alert(data); return;
                if (data.error) {
                  $("#divExibirCondicoes").html("");
                  dialogAlert(strInformacao, data.error.msg, 6);
                  return;
                }

                $("#divExibirCondicoes").html(data.strHtml);

                //Apenas números input css
                $(".numericOnly").on("keypress keyup blur", function (event) {
                  $(this).val(
                    $(this)
                      .val()
                      .replace(/[^A-Z\.][^0-9\.]/g, "")
                  );
                  if (
                    (event.which != 46 || $(this).val().indexOf(".") != -1) &&
                    (event.which < 48 || event.which > 57)
                  ) {
                    event.preventDefault();
                  }
                });

                $("#btnAdicionarRapido").prop("disabled", false);
              })
              .fail(function (data) {
                $("#btnAdicionarRapido").prop("disabled", false);
                $("#divExibirCondicoes").html("");
                dialogAlert(strAtencao, data.responseText, 6);
              });
          } else {
            $("#btnAdicionarRapido").prop("disabled", false);
            $("#divExibirCondicoes").html("");
          }
        });
      }, 1000);
    })
    .fail(function (data) {
      $(".btn-formulario, .btn-pesquisar, #btnAdicionarCondicaoRapido").prop(
        "disabled",
        false
      );
      $("#btnAdicionarCondicaoRapido").html(strLabel);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarCondicoesPagamentosRapido(intIdentificador) {
  if ($.trim($("#COP_DescricaoRapido").val()) == "") {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else if (
    $.trim($("#COP_QuantidadeParcelasRapido").val()) == "" ||
    $.trim($("#COP_QuantidadeParcelasRapido").val()) == 0
  ) {
    $.notify("Descrição precisa ser informada.", "warn");
    return;
  } else {
    var arrCondicoes = new Array();
    var bolPreencheuTodos = true;

    $("input[name='COP_Condicao[]']").each(function () {
      if ($.trim($(this).val()) == "") {
        bolPreencheuTodos = false;
      }

      arrCondicoes.push($(this).val());
    });

    if (bolPreencheuTodos == false) {
      $.notify("Todas as condições precisam ser informadas.", "warn");
      return;
    }

    $(".btn-formulario, #btnAdicionarRapido").prop("disabled", true);
    var strLabel = $("#btnAdicionarRapido").html();
    $("#btnAdicionarRapido").html(strCarregando);

    $.ajax({
      url: $.trim($("#condicoes_pagamentos_salvar_rapido").val()),
      dataType: "json",
      cache: false,
      data: {
        SGP_ID: $.trim($("#hddIdentificador").val()),
        COP_Descricao: $.trim($("#COP_DescricaoRapido").val()),
        COP_QuantidadeParcelas: $.trim(
          $("#COP_QuantidadeParcelasRapido").val()
        ),
        COP_Condicao: arrCondicoes,
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario, #btnAdicionarRapido").prop("disabled", false);
        $("#btnAdicionarRapido").html(strLabel);

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $(".modal").modal("hide");
        $.notify(data.mensagem, "success");

        $("#" + data.strCampo).append(
          "<option selected value='" +
          data.intCodigo +
          "'>" +
          data.strDescricao +
          "</option>"
        );
      })
      .fail(function (data) {
        $(".btn-formulario, #btnAdicionarRapido").prop("disabled", false);
        $("#btnAdicionarRapido").html(strLabel);

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

$(document).ready(function () {
  $("#informacoes-parcelas").html(strCarregando);

  $("#CTB_QuantidadeParcelas").keyup(function () {
    if ($.trim(this.value) != "") {
      $.ajax({
        url: $.trim(
          $("#contas_pagar_gerar_parcelas_visualizar_devolucao").val()
        ),
        dataType: "json",
        cache: false,
        data: {
          SGP_QuantidadeParcelas: $.trim(this.value),
          SGP_ValorTotal: $.trim($("#CTB_ValorDevolvido").val()),
          SGP_Vencimento: $.trim($("#CTB_PrimeiroVencimento").val()),
          SGP_Acao: $.trim($("#hddAcaoEscondido").val()),
          SGP_ID: $.trim($("#hddCodigoEscondido").val()),
        },
        type: "POST",
      })
        .success(function (data) {
          if (data.error) {
            $("#informacoes-parcelas").html("");
            dialogAlert(strInformacao, data.error.msg, 6);
            return;
          }

          $("#informacoes-parcelas").html(data.strHtml);
          setInitFunctions();
        })
        .fail(function (data) {
          $("#informacoes-parcelas").html("");
          dialogAlert(strAtencao, data.responseText, 6);
        });
    } else {
      $("#informacoes-parcelas").html("");
    }
  });

  $("#CTB_QuantidadeParcelas").trigger("keyup");
});

function carregarFinanceiroContasPagarParcelas() {
  $("#informacoes-parcelas").html(strCarregando);

  $("#SGP_Periodicidade").change(function () {
    if (
      $.trim(this.value) != "" &&
      $.trim($("#SGP_QuantidadeParcelas").val()) != ""
    ) {
      $("#SGP_QuantidadeParcelas").trigger("keyup");
    }
  });

  $("#SGP_DataVencimentoInicial").change(function () {
    if (
      $.trim(this.value) != "" &&
      $.trim($("#SGP_QuantidadeParcelas").val()) != ""
    ) {
      $("#SGP_QuantidadeParcelas").trigger("keyup");
    }
  });

  $("#SGP_CondicaoPagamento").on("change", function (e) {
    e.preventDefault();
    if ($("#SGP_CondicaoPagamento").is(":checked")) {
      $("input[name='SGP_DataVencimento[]']").prop("disabled", true);
      $("input[name='SGP_ValorParcela3[]']").prop("disabled", true);
      $("#SGP_QuantidadeParcelas").val(
        $.trim($("#hddQuantidadeCondicaoPagamento").val())
      );
      $(
        "#SGP_Periodicidade, #SGP_QuantidadeParcelas, #SGP_DataVencimentoInicial"
      ).prop("disabled", true);
    } else {
      $("input[name='SGP_DataVencimento[]']").prop("disabled", false);
      $("input[name='SGP_ValorParcela3[]']").prop("disabled", false);
      $(
        "#SGP_Periodicidade, #SGP_QuantidadeParcelas, #SGP_DataVencimentoInicial"
      ).prop("disabled", false);
    }

    $("#SGP_Periodicidade").trigger("chosen:updated");
    $("#SGP_QuantidadeParcelas").trigger("keyup");
  });

  $("#SGP_QuantidadeParcelas").on("keyup", function () {
    $("#informacoes-parcelas").html(strCarregando);

    if ($.trim(this.value) != "") {
      var strSelecionado = strNao;
      if ($("#SGP_CondicaoPagamento").is(":checked")) {
        strSelecionado = strSim;
      }

      $.ajax({
        url: $.trim($("#contas_pagar_gerar_parcelas_visualizar").val()),
        dataType: "json",
        cache: false,
        data: {
          SGP_DataEmissao: $.trim($("#SGP_DataEmissao").val()),
          SGP_DataVencimentoInicial: $.trim(
            $("#SGP_DataVencimentoInicial").val()
          ),
          SGP_Periodicidade: $.trim($("#SGP_Periodicidade").val()),
          SGP_QuantidadeParcelas: $.trim(this.value),
          SGP_ValorTotal: $.trim($("#hddValorEscondido").val()),
          SGP_Acao: $.trim($("#hddAcaoEscondido").val()),
          SGP_ID: $.trim($("#hddCodigoEscondido").val()),
          COP_ID: $.trim($("#COP_ID").val()),
          SGP_CondicaoPagamento: $.trim(strSelecionado),
        },
        type: "POST",
      })
        .success(function (data) {
          if (data.error) {
            $("#informacoes-parcelas").html("");
            dialogAlert(strInformacao, data.error.msg, 6);
            return;
          }

          $("#informacoes-parcelas").html(data.strHtml);
          $("select[name='SGP_FormaPagamento3[]']").chosen();
          setInitFunctions();
        })
        .fail(function (data) {
          $("#informacoes-parcelas").html("");
          dialogAlert(strAtencao, data.responseText, 6);
        });
    } else {
      $("#informacoes-parcelas").html("");
    }
  });

  setTimeout(function () {
    $("#SGP_CondicaoPagamento").trigger("change");
  }, 500);
}

function comproTerrenoAdicionarFormulario(intCodigo) {
  $("#addButton").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: $.trim($("#terrenos_formulario_modal_campo").val()),
    dataType: "json",
    cache: false,
    data: {
      TFO_ID: $.trim(intCodigo),
    },
    type: "POST",
  })
    .success(function (data) {
      $("#addButton").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      dialogAlert(data.strTitulo, data.strHtml, 3);

      setTimeout(function () {
        setInitFunctions();

        $("#TFO_TipoCampo").change(function () {
          if ($("#TFO_TipoCampo").val() == "lista") {
            document.getElementById("tfo_textarea").style.display = "block";
            document.getElementById("placeholder").innerHTML = "Lista";

            $("#TFO_PlaceholderCampo").hide();
          } else {
            document.getElementById("tfo_textarea").style.display = "none";
            document.getElementById("placeholder").innerHTML = "Placeholder";
            $("#TFO_PlaceholderCampo").show();
          }
        });
      }, 1000);
    })
    .fail(function (data) {
      $("#addButton").prop("disabled", false);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function comproTerrenoSalvarFormulario() {
  if ($.trim($("#TFO_TipoCampo").val()) == "") {
    $.notify("Tipo do campo precisa ser informado.", "warn");
    return;
  } else if ($.trim($("#TFO_NomeCampo").val()) == "") {
    $.notify("Nome do campo precisa ser informado.", "warn");
    return;
  } else {
    $("#btnSalvarDialog").prop("disabled", true);
    var strLabel = $("#btnSalvarDialog").html();
    $("#btnSalvarDialog").html(strCarregando);

    $.ajax({
      url: $.trim($("#terrenos_formulario_salvar_campo").val()),
      dataType: "json",
      cache: false,
      data: {
        TFO_ID: $.trim($("#hddTFO_ID").val()),
        TFO_TipoCampo: $.trim($("#TFO_TipoCampo").val()),
        TFO_NomeCampo: $.trim($("#TFO_NomeCampo").val()),
        TFO_PlaceholderCampo: $.trim($("#TFO_PlaceholderCampo").val()),
        TFO_TextArea: $.trim($("#tfo_textarea").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $("#btnSalvarDialog").prop("disabled", false);
        $("#btnSalvarDialog").html(strLabel);
        preLoadingClose();
        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $(".modal").modal("hide");
        $.notify(data.mensagem, "success");

        setTimeout(function () {
          redir("", "parent");
        }, 1500);
      })
      .fail(function (data) {
        $("#btnSalvarDialog").prop("disabled", false);
        $("#btnSalvarDialog").html(strLabel);
        preLoadingClose();
        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function adicionarNovoInsumoRapido() {
  $(".btn-filtro").prop("disabled", true);

  $.ajax({
    url: $.trim($("#hddInsumosNovoRapido").val()),
    dataType: "json",
    cache: false,
    type: "POST",
  }).success(function (data) {
      $(".btn-filtro").prop("disabled", false);
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      dialogAlert2(data.strTitulo, data.strHtml, 3, "dialogNovoRapidoInsumos");

    }).fail(function (data) {
      $(".btn-filtro").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function checarFavoritos(intCodigo) {
  $(".btn-formulario, .btn-filtro").prop("disabled", true);

  $("#spnFavoritos").html(strCarregandoIcone);

  $.ajax({
    url: $.trim($("#rotas_favoritos").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      SGP_Valor: true,
      ROT_ID: $.trim(intCodigo),
    },
  })
    .success(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);

      if (data.error) {
        $("#spnFavoritos").html("<i class='fa fa-star-o'></i>");
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#spnFavoritos").html(data.favoritos);
      $.notify(data.mensagem, "info");
    })
    .fail(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);
      $("#spnFavoritos").html("<i class='fa fa-star-o'></i>");
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function cotacoesAtualizarDescontos(intCodigo, douValor) {
  var strItem = $.trim(atob(intCodigo));
  var valor = $("#spnValorCalculado" + strItem).html();

  if (douValor != $.trim($('#CIF_Desconto' + strItem).attr('valor'))) {
    $(".camposDescontos").prop("disabled", true);
    $("#spnValorCalculado" + strItem + ", #tdValorTotalDescontado, #tdValorTotalSelecionado").html(strCarregandoIcone);
    $('#CIF_Desconto' + strItem).attr('valor', douValor);

    $.ajax({
      url: $.trim($("#cotacoes_adicionar_salvar").val()),
      dataType: "json",
      cache: false,
      type: "POST",
      data: {
        CIF_ID: $.trim(intCodigo),
        COT_ID: $.trim($("#COT_ID").val()),
        CIF_Desconto: $.trim(douValor)
      },
    }).success(function (data) {
      $(".camposDescontos").prop("disabled", false);

      if (data.error) {
        $("#spnValorCalculado" + strItem).html(valor);
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $.notify(data.mensagem, "success");
      $("#spnValorCalculado" + strItem).html(data.douValorCalculado);

      $("#descontofornecedor" + data.intCodigo).html(data.douDescontoCalculado);
      $("#totalfornecedor" + data.intCodigo).html(data.douTotal);
      $("#calculadofornecedor" + data.intCodigo).html(data.douTotalCalculado);
      $("#btnAtualizar").trigger("click");

    }).fail(function (data) {
      $(".camposDescontos").prop("disabled", false);
      $("#spnValorCalculado" + strItem).html(valor);
      dialogAlert(strAtencao, data.responseText, 6);
    });
  }

}


function initAumentarModal(valor = '95%') {
  $('.modal-dialog').css('width', valor);
}

function ExportToExcel(mytblId) {
  var htmltable = document.getElementById(mytblId);
  var html = htmltable.outerHTML;
  window.open("data:application/vnd.ms-excel," + encodeURIComponent(html));
}

function exportTableToCSV($table, filename) {
  var $rows = $table.find("tr:has(td),tr:has(th)"),
    // Temporary delimiter characters unlikely to be typed by keyboard
    // This is to avoid accidentally splitting the actual contents
    tmpColDelim = String.fromCharCode(11), // vertical tab character
    tmpRowDelim = String.fromCharCode(0), // null character
    // actual delimiter characters for CSV format
    colDelim = '","',
    rowDelim = '"\r\n"',
    // Grab text from table into CSV formatted string
    csv =
      '"' +
      $rows
        .map(function (i, row) {
          var $row = $(row),
            $cols = $row.find("td,th");

          return $cols
            .map(function (j, col) {
              var $col = $(col),
                text = $col.text();

              return text.replace(/"/g, '""'); // escape double quotes
            })
            .get()
            .join(tmpColDelim);
        })
        .get()
        .join(tmpRowDelim)
        .split(tmpRowDelim)
        .join(rowDelim)
        .split(tmpColDelim)
        .join(colDelim) +
      '"',
    // Data URI
    csvData = "data:application/csv;charset=utf-8," + encodeURIComponent(csv);

  if (window.navigator.msSaveBlob) {
    // IE 10+
    //alert('IE' + csv);
    window.navigator.msSaveOrOpenBlob(
      new Blob([csv], { type: "text/plain;charset=utf-8;" }),
      "csvname.csv"
    );
  } else {
    $(this).attr({ download: filename, href: csvData, target: "_blank" });
  }
}

function download_csv(csv, filename) {
  var csvFile;
  var downloadLink;

  // CSV FILE
  csvFile = new Blob([csv], { type: "text/csv" });

  // Download link
  downloadLink = document.createElement("a");

  // File name
  downloadLink.download = filename;

  // We have to create a link to the file
  downloadLink.href = window.URL.createObjectURL(csvFile);

  // Make sure that the link is not displayed
  downloadLink.style.display = "none";

  // Add the link to your DOM
  document.body.appendChild(downloadLink);

  // Lanzamos
  downloadLink.click();
}

function export_table_to_csv(html, filename) {
  var csv = [];
  //var rows = document.querySelectorAll("table tr");

  var rows = document.getElementById(html);

  for (var i = 0; i < rows.length; i++) {
    var row = [],
      cols = rows[i].querySelectorAll("td, th");

    for (var j = 0; j < cols.length; j++) row.push(cols[j].innerText);

    csv.push(row.join(";"));
  }

  // Download CSV
  download_csv(csv.join("\n"), filename);
}

// Load pagination
function loadPagination(
  url,
  pagno,
  arrFiltros,
  html = "pagination",
  dados = "consultar-dados",
  css = ""
) {
  $(".btn-formulario, .btn-filtro").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: url + "/" + pagno + "/" + html + "/" + dados + "/" + css,
    dataType: "json",
    cache: false,
    type: "POST",
    data: JSON.parse(arrFiltros),
  })
    .success(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#" + dados).html(data.strHtml);
      $("#" + html).html(data.pagination);

      if ($(".selectSelectpicker") !== undefined){
        $(".selectSelectpicker").selectpicker("refresh");
      }

      // Detect pagination click
      $("#" + html).on("click", "a", function (e) {
        e.preventDefault();
        var pageno = $(this).attr("data-ci-pagination-page");
        loadPagination(data.url, pageno, data.arrFiltros, html, dados, css);
      });

      setInitFunctions();
    })
    .fail(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);
      preLoadingClose();

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function orderByPagination(
  url,
  coluna,
  pagination = "pagination",
  dados = "consultar-dados",
  arrFiltros = ""
) {
  $(".btn-formulario, .btn-filtro").prop("disabled", true);
  preLoadingOpen();

  $.ajax({
    url: url + "/",
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      SGP_Coluna: $.trim(coluna),
      SGP_Paginacao: $.trim($("#SGP_Paginacao").val()),
    },
  })
    .success(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $("#" + dados).html(data.strHtml);
      $("#" + pagination).html(data.pagination);
      $("#" + pagination).on("click", "a", function (e) {
        e.preventDefault();
        var pageno = $(this).attr("data-ci-pagination-page");
        loadPagination(data.url, pageno, data.arrFiltros, pagination, dados);
      });
    })
    .fail(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function formularioCodigoContabilIntegracao(strTipo, strTabela, strLabel) {
  $(".btn-formulario").prop("disabled", true);

  $.ajax({
    url: $.trim($("#conta_contabil_integracao_adicionar").val()),
    dataType: "json",
    cache: false,
    data: {
      CCI_Tipo: $.trim(strTipo),
      CCI_Tabela: $.trim(strTabela),
      SGP_Label: $.trim(strLabel),
      EMP_ID: $.trim($("#EMP_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      dialogAlert2(
        data.strTitulo,
        data.strHtml,
        3,
        "dialogFormularioCdigoContabil"
      );

      setTimeout(function () {
        setInitFunctions();
      }, 1000);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function formularioCodigoContabilIntegracaoPortalContabilidade(
  strTipo,
  strTabela
) {
  $(".btn-formulario").prop("disabled", true);

  $.ajax({
    url: $.trim($("#contadores_conta_contabil_integracao_adicionar").val()),
    dataType: "json",
    cache: false,
    data: {
      CCI_Tipo: $.trim(strTipo),
      CCI_Tabela: $.trim(strTabela),
      EMP_ID: $.trim($("#EMP_ID").val()),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      dialogAlert2(
        data.strTitulo,
        data.strHtml,
        3,
        "dialogFormularioCdigoContabil"
      );

      setTimeout(function () {
        setInitFunctions();
      }, 1000);
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarCodigoContabilIntegracao() {
  $(".btn-formulario").prop("disabled", true);
  var strLabel = $("#btnSalvarDialog").html();
  $("#btnSalvarDialog").html(strCarregando);

  $.ajax({
    url: $.trim($("#conta_contabil_integracao_salvar").val()),
    dataType: "json",
    cache: false,
    data: $("#frmFormularioDialog").serialize(),
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvarDialog").html(strLabel);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $(".modal").modal("hide");
      $.notify(data.mensagem, "success");
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvarDialog").html(strLabel);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function salvarCodigoContabilIntegracaoPortalContabilidade() {
  $(".btn-formulario").prop("disabled", true);
  var strLabel = $("#btnSalvarDialog").html();
  $("#btnSalvarDialog").html(strCarregando);

  $.ajax({
    url: $.trim($("#contadores_conta_contabil_integracao_salvar").val()),
    dataType: "json",
    cache: false,
    data: $("#frmFormularioDialog").serialize(),
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvarDialog").html(strLabel);

      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      $(".modal").modal("hide");
      $.notify(data.mensagem, "success");
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvarDialog").html(strLabel);

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function carregarQuantidadeArquivos(intQuantidade) {
  $("#div-anexos").html(strCarregando);
  if (intQuantidade > 0) {
    $(".btn-formulario, .btn-filtro").prop("disabled", true);

    $.ajax({
      url:
        $.trim($("#contas_pagar_baixa_exibir_arquivos").val()) +
        "/" +
        $.trim(intQuantidade),
      dataType: "json",
      cache: false,
      data: {
        SGP_Data: true,
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario, .btn-filtro").prop("disabled", false);

        if (data.error) {
          $("#div-anexos").html("");
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $("#div-anexos").html(data.strHtml);
      })
      .fail(function (data) {
        $(".btn-formulario, .btn-filtro").prop("disabled", false);
        dialogAlert(strAtencao, data.responseText, 6);
      });
  } else {
    $("#div-anexos").html("");
  }
}

function adicionarOpcoesEmpresas(input, multiplos = false) {
  $(".btn-formulario, .btn-filtro").prop("disabled", true);

  $("#" + input).multiselect("destroy");
  $("#" + input).html("");

  $.ajax({
    url: $.trim($("#empresas_carregar").val()),
    dataType: "json",
    cache: false,
    data: {
      SGP_Data: true,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);
      $("#linkAdicionarOpcoesEmpresas").hide();

      if (data.error) {
        $("#" + input).multiselect(getOptionsSelect());
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      var strHtml = "";
      if (!multiplos) {
        strHtml += "<option selected value=''>" + strSelecione + "</option>";
      }

      if (data.arrDados.length > 0) {
        for (var i = 0; i < data.arrDados.length; i++) {
          strHtml +=
            "<option value='" +
            data.arrDados[i].EMP_ID +
            "'>" +
            data.arrDados[i].EMP_NomeFantasia +
            "</option>";
        }
      }

      $("#" + input).append(strHtml);
      $("#" + input).multiselect(getOptionsSelect());
    })
    .fail(function (data) {
      $("#linkAdicionarOpcoesEmpresas").hide();
      $("#" + input).multiselect(getOptionsSelect());

      $(".btn-formulario, .btn-filtro").prop("disabled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function getOptions(carregarClick = true) {
  return {
    enableFiltering: true,
    enableCaseInsensitiveFiltering: true,
    enableFullValueFiltering: false,
    filterPlaceholder: "Pesquisar...",
    numberDisplayed: 1,
    selectedList: 1,
    maxHeight: 300,
    keepOrder: true,
    autoOpen: true,
    includeSelectAllOption: true,
    customFilter: function customFilter(label, text) { },
    onFilter: function () { },
    onDropdownShow: function (event) { },
    onDropdownHide: function (event) { },
    onDropdownHidden: function (event) { },
    onDropdownShown: function (event) {
      if (carregarClick) {
        var selectID = $.trim(this.$select[0].id);
        var rota = "";

        if ($("#" + selectID) != undefined) {
          var alt = $("#" + selectID).attr("alt");
          var carregar = false;
          var campo = "";

          if (selectID == "INC_ID") {
            rota = $.trim($("#hiperdados_inconporadoras_carregar").val());
          } else if (selectID == "CON_ID" && alt == "construtoras") {
            rota = $.trim($("#hiperdados_construtoras_carregar").val());
          } else if (selectID == "VEN_ID") {
            rota = $.trim($("#hiperdados_vendedores_carregar").val());
          } else if (selectID == "EMP_ID") {
            rota = $.trim($("#empresas_carregar").val());
          } else if (selectID == "INS_ID") {
            rota = $.trim($("#insumos_carregar").val());
          } else if (selectID == "PLF_Conta") {
            rota = $.trim($("#plano_financeiro_carregar").val());
          } else if (selectID == "CEN_ID") {
            rota = $.trim($("#centro_custos_carregar").val());
          } else if (selectID == "ORC_ID") {
            rota = $.trim($("#orcamentos_carregar").val());
          } else if (selectID == "COR_ID" && alt == "corretorescomercial") {
            rota = $.trim($("#propostas_corretores_carregar").val());
          } else if (selectID == "EST_ID") {
            rota = $.trim($("#estruturas_carregar").val());
            carregar = true;
            campo = "UNI_ID";
          } else if (selectID == "UNI_ID") {
            rota = $.trim($("#estruturas_unidades_carregar").val());
          } else if (selectID == "CTO_ID") {
            rota = $.trim($("#carteiras_contratos_carregar").val());
          } else if (selectID == "IND_ID") {
            rota = $.trim($("#indexadores_carregar").val());
          } else if (selectID == "CON_ID" && alt == "contasbancarias") {
            rota = $.trim($("#contas_bancarias_carregar").val());
          }

          if ($.trim(rota) != "") {
            var selected = [];
            $("#" + selectID + " :selected").each(function () {
              selected[$(this).val()] = $(this).val();
            });

            if (selected.length == 0) {
              adicionarOpcoesRotasTabela(
                rota,
                selectID,
                true,
                carregar,
                null,
                campo
              );
            }
          }
        }
      }
    },
  };
}

function checarBtnProgramar() {
  $("#btnProgramar").hide();
  $(".marcar").each(function () {
    if (this.checked) {
      $("#btnProgramar").show();
      return;
    }
  });
}

function carregarRotaSecundaria(rota, arrSelecionados = null) {
  $.ajax({
    url: $.trim(rota),
    dataType: "json",
    cache: false,
    data: {
      SGP_Data: true,
      SGP_Input: arrSelecionados,
    },
    type: "POST",
  })
    .success(function (data) {
      if (data.error) {
        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      if (data.strCampo != undefined && data.arrDados != undefined) {
        $("#" + data.strCampo).multiselect("destroy");
        $("#" + data.strCampo).multiselect(getOptions(false));
        $("#" + data.strCampo).multiselect("refresh");

        var strHtml = "";
        if (data.arrDados.length > 0) {
          for (var i = 0; i < data.arrDados.length; i++) {
            strHtml +=
              "<option value='" +
              data.arrDados[i].ID +
              "'>" +
              data.arrDados[i].DESCRICAO +
              "</option>";
          }
        }

        $("#" + data.strCampo).append(strHtml);
        $("#" + data.strCampo).multiselect("rebuild");
      }
    })
    .fail(function (data) {
      if (data.strCampo != undefined) {
        $("#" + data.strCampo).multiselect("refresh");
      }

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function adicionarOpcoesRotasTabela(
  rota,
  input,
  multiplos = true,
  carregar = false,
  arrSelecionados = null,
  campo = null
) {
  $(".btn-formulario, .btn-filtro").prop("disabled", true);
  $("#" + input).html("");

  $.ajax({
    url: $.trim(rota),
    dataType: "json",
    cache: false,
    data: {
      SGP_Data: true,
      SGP_Input: arrSelecionados,
      SGP_Carregar: carregar,
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);

      if (carregar) {
        $("#" + input).on("change", function (event) {
          var arrSelecionados = new Array();
          $("select[name='" + input + "[]'] option:selected").each(function () {
            arrSelecionados.push($(this).val());
          });

          if (arrSelecionados.length == 0) {
            $("#" + campo).multiselect("destroy");
            $("#" + campo).html("");
            $("#" + campo).multiselect(getOptions(false));
            $("#" + campo).multiselect("refresh");
          }   else{
            carregarRotaSecundaria(rota, arrSelecionados);
          }
        });
      }

      if (data.error) {
        if (multiplos) {
          $("#" + input).multiselect("refresh");
        }

        dialogAlert(strInformacao, data.error.msg, 6);
        return;
      }

      var strHtml = "";
      if (!multiplos) {
        strHtml += "<option value=''>" + strSelecione + "</option>";
      }

      if (data.arrDados.length > 0) {
        for (var i = 0; i < data.arrDados.length; i++) {
          strHtml +=
            "<option value='" +
            data.arrDados[i].ID +
            "'>" +
            data.arrDados[i].DESCRICAO +
            "</option>";
        }
      }

      $("#" + input).append(strHtml);

      if (multiplos) {
        $("#" + input).multiselect("rebuild");
      } else {
        $("#" + input).chosen();
      }
    })
    .fail(function (data) {
      $(".btn-formulario, .btn-filtro").prop("disabled", false);

      if (multiplos) {
        $("#" + input).multiselect("refresh");
      }

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function carregarModulosTiposCadastrosAuxiliares(input, inputTipo) {
  $(document).ready(function () {
    $("#" + input).change(function () {
      var strHtml = "";

      $(".btn-formulario").prop("disabled", true);
      $("#" + inputTipo).multiselect("destroy");

      var arrSelecionados = new Array();
      $("select[name='" + input + "[]'] option:selected").each(function () {
        arrSelecionados.push($(this).val());
      });

      $.ajax({
        url: $.trim($("#cadastros_auxiliares_carregar").val()),
        dataType: "json",
        cache: false,
        data: {
          MOD_ID: arrSelecionados,
        },
        type: "POST",
      })
        .success(function (data) {
          $(".btn-formulario").prop("disabled", false);

          if (data.error) {
            dialogAlert(strInformacao, data.error.msg, 6);
            return;
          }

          if (data.arrDados != null) {
            for (var x = 0; x < data.arrDados.length; x++) {
              var strSelected = "";

              if (
                $.inArray(data.arrDados[x].TCX_ID, data.arrSelecionados) >= 0
              ) {
                strSelected = " selected ";
              }

              strHtml +=
                "<option " +
                strSelected +
                " value='" +
                data.arrDados[x].TCX_ID +
                "'>" +
                data.arrDados[x].TCX_Descricao +
                " (" +
                data.arrDados[x].MOD_Descricao +
                ")</option>";
            }
          }

          $("#" + inputTipo).html(strHtml);
          $("#" + inputTipo).multiselect(getOptions());
          $("#" + inputTipo).multiselect("refresh");
        })
        .fail(function (data) {
          $(".btn-formulario").prop("disabled", false);
          $("#" + inputTipo).html("");
          $("#" + inputTipo).multiselect("refresh");

          dialogAlert(strAtencao, data.responseText, 6);
        });
    });
  });
}

function enterPesquisarCadastrosGeraisCadastrosAuxiliares(e) {
  if (e.keyCode == 13) {
    cadastrosGeraisConsultarCadastrosAuxiliares();
  }
}

function enterPesquisarCadastrosGeraisInsumos(e) {
  if (e.keyCode == 13) {
    cadastrosGeraisConsultarInsumos();
  }
}

function enterPesquisarCadastrosGeraisEmpresas(e) {
  if (e.keyCode == 13) {
    cadastrosGeraisConsultarEmpresas();
  }
}

function enterPesquisarCadastrosGeraisUnidadesMedidas(e) {
  if (e.keyCode == 13) {
    cadastrosGeraisConsultarUnidadesMedidas();
  }
}

function consultarPadraoInicial(filtro = true) {
  if (filtro) {
    $(".btn-filtro").prop("disabled", true);
    $(".btn-impressao").hide();

    $("#spnTotalRegistrosConsultar").show();
    $("#spnTotalRegistrosConsultar").html(strCarregandoIcone);
    var strLabel = $("#btnFiltrar").html();
    $("#btnFiltrar, #consultar-dados").html(strCarregando);
    preLoadingOpen();

    return strLabel;
  } else {
    $(".btn-formulario").prop("disabled", true);
    var strLabel = $("#btnSalvar").html();
    $("#btnSalvar").html(strCarregando);
    preLoadingOpen();

    return strLabel;
  }
}

function consultarPadraoExcessao() {
  $("#consultar-dados, #spnTotalRegistrosConsultar").html("");
}

function consultarPadraoFalha(strLabel, filtro = true) {
  if (filtro) {
    $(".btn-filtro").prop("disabled", false);
    $("#btnFiltrar").html(strLabel);
    $("#spnTotalRegistrosConsultar, #consultar-dados").html("");
  } else {
    $(".btn-formulario").prop("disabled", false);
    $("#btnSalvar").html(strLabel);
  }

  preLoadingClose();
}

function consultarPadraoSucesso(strLabel, filtro = true) {
  if (filtro) {
    $(".btn-filtro").prop("disabled", false);
    $("#btnFiltrar").html(strLabel);
  } else {
    $(".btn-formulario").prop("disabled", false);
    $("#btnSalvar").html(strLabel);
  }

  preLoadingClose();
}

function consultarPadraoSucessoPaginacao(
  data,
  dataTables = false,
  filtro = true
) {
  if (filtro) {
    console.log('filtrodata', data);

    $("#consultar-dados").html(data.strHtml);
    $("#spnTotalRegistrosConsultar").html(data.totalRegistros);
    $("#pagination").html(data.pagination);
    $(".btn-impressao").hide();

    $("#pagination").on("click", "a", function (e) {
      e.preventDefault();
      var pageno = $(this).attr("data-ci-pagination-page");
      loadPagination(data.url, pageno, data.arrFiltros);
    });

    if (data.totalRegistros > 0) {
      $(".btn-impressao").show();

      if (dataTables) {
        requireDataTables(false, true, true, true, true, false, true);
      }
    }

    setInitFunctions();
  } else {
    if (data.mensagem != undefined) {
      $.notify(data.mensagem, "success");
    }
  }
}

function comprasDocumentosAtualizarObservacoes() {
  if ($.trim($("#DOC_ID").val()) != "") {
    $(".btn-formulario").prop("disabled", true);

    preLoadingOpen();

    $.ajax({
      url:
        $.trim($("#documentos_atualizar_observacoes").val()) +
        "/" +
        $.trim($("#DOC_ID").val()),
      dataType: "json",
      cache: false,
      data: {
        DOC_Observacoes: $.trim($("#DOC_Observacoes").val()),
      },
      type: "POST",
    })
      .success(function (data) {
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();

        if (data.error) {
          dialogAlert(strInformacao, data.error.msg, 6);
          return;
        }

        $.notify(data.mensagem, "success");
      })
      .fail(function (data) {
        $(".btn-formulario").prop("disabled", false);
        preLoadingClose();

        dialogAlert(strAtencao, data.responseText, 6);
      });
  }
}

function enterPesquisar(e, acao = "") {
  if (e.keyCode == 13) {
    $("#btnFiltrar").trigger("click");
  }
}

function importarComposicoes(composicoesID) {
  var erros = 0;
  var campo = [];

  /*var form_data = new FormData();

  form_data.append('file', $('#arquivo').prop('files')[0]);             */

  $(".require").each(function () {
    //$(this).val() == "" ?  : "";
    if ($(this).val() == "") {
      //campo.push(this.getAttribute("data-campo"));
      erros++;
    }
  });

  if (erros > 0) {
    //campo = $(".require")().attr("data-campo");
    $.notify("Preencha os campos corretamente!", "warn");
    var response = "Preencha o campo " + campo[0] + " corretamente!";
  } else {
    // $('#btn-importar').prop('disabled', true);
    // $('#btn-importar').html('aguarde', true);

    var formData = new FormData(document.getElementById("formdeimportacao"));

    $.ajax({
      url: $.trim($("#planejamento_composicoes_importar").val()),
      type: "POST",
      dataType: "json",
      data: formData,
      success: function (data) {
        $("#modalBootstrapDialogDetail").modal("toggle");
        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
        } else {
          //alert(data)
          // dialogAlert(strAtencao, data.success.msg, 6);
          //$('#modalBootstrapDialogDetail').modal('toggle');
          $.notify(data.success.msg, "success");
          $("#btnAtualizar").trigger("click");
        }
      },
      cache: false,
      contentType: false,
      processData: false,
      xhr: function () {
        // Custom XMLHttpRequest
        var myXhr = $.ajaxSettings.xhr();
        if (myXhr.upload) {
          // Avalia se tem suporte a propriedade upload
          myXhr.upload.addEventListener(
            "progress",
            function () {
              /* faz alguma coisa durante o progresso do upload */
              //$('#modalBootstrapDialogDetail').modal('toggle');
              //preLoadingOpen();
            },
            false
          );
        }
        return myXhr;
      },
    });
  }
}

function carregarCidadesPorUF(inputUF, inputCidade, arrCidadesSelecionadas) {
  $(".btn-filtro, .btn-formulario").prop("disbled", true);

  $.ajax({
    url: $.trim($("#sistemas_carregar_cidades_por_uf").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      UF_ID: $.trim($("#" + inputUF).val()),
      CID_ID: $.trim($("#" + inputCidade).val()),
      CID_Selecionadas_ID: arrCidadesSelecionadas,
    },
  })
    .success(function (data) {
      $(".btn-filtro, .btn-formulario").prop("disbled", false);

      if (data.error) {
        consultarPadraoExcessao();
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      if (data.arrDados != undefined) {
        $("#" + inputCidade).selectpicker("refresh");
        var strHtml = "<option>" + strSelecione + "</option>";
        for (var i = 0; i < data.arrDados.length; i++) {
          var strSelected = "";
          if ($.inArray(data.arrDados[i].CID_ID, data.arrSelecionados) >= 0) {
            strSelected = " selected ";
          }

          strHtml +=
            "<option " +
            strSelected +
            " value='" +
            data.arrDados[i].CID_ID +
            "'>" +
            data.arrDados[i].CID_Descricao +
            "</option>";
        }

        $("#" + inputCidade).html(strHtml);
        $("#" + inputCidade).selectpicker("refresh");
      }
    })
    .fail(function (data) {
      $(".btn-filtro, .btn-formulario").prop("disbled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function initEmpresasXContasBancarias(filtro = true) {
  //Carrega as condições das unidades selecionadas
  if (filtro) {
    $("#EMP_ID").on("change", function () {
      var arrSelecionadas = new Array();

      $("select[name='EMP_ID[]'] option:selected").each(function () {
        arrSelecionadas.push($(this).val());
      });

      if (arrSelecionadas.length > 0) {
        carregarContasBancariasEmpresasMultiplos(arrSelecionadas);
      }
    });
  } else {
    /*clearChosen();

    $('#EMP_ID, #CON_ID').chosen('destroy');
    $('#EMP_ID, #CON_ID').prop("selectedindex", -1);   
    $('#EMP_ID, #CON_ID').chosen();
    $("#EMP_ID, #CON_ID").addClass('chosen');*/

    $("#EMP_ID").change(function () {
      if ($.trim(this.value) != "") {
        $.ajax({
          url: $.trim($("#hddEmpresasDados").val()),
          dataType: "json",
          cache: false,
          type: "POST",
          data: {
            EMP_ID: $.trim(this.value),
          },
        })
          .success(function (data) {
            $(".btn-filtro, .btn-formulario").prop("disbled", false);

            if (data.error) {
              consultarPadraoExcessao();
              dialogAlert(strAtencao, data.error.msg, 6);
              return;
            }

            if (data.sucesso == "true") {
              var strHtml = "<option value=''>" + strSelecione + "</option>";

              for (var i = 0; i < data.arrContasBancarias.length; i++) {
                var strSelected = "";
                if (data.arrContasBancarias.length == 1) {
                  strSelected = "selected";
                }

                strHtml +=
                  "<option " +
                  strSelected +
                  " value='" +
                  data.arrContasBancarias[i].CON_ID +
                  "'>" +
                  data.arrContasBancarias[i].CON_Descricao +
                  "</option>";
              }

              $("#CON_ID").html(strHtml);
            } else {
              $("#CON_ID").html(
                "<option value=''>" + strSelecione + "</option>"
              );
              dialogAlert(strAtencao, data.mensagem, 6);
            }

            $("#CON_ID").trigger("chosen:updated");
          })
          .fail(function (data) {
            $(".btn-filtro, .btn-formulario").prop("disbled", false);
            dialogAlert(strAtencao, data.responseText, 6);
          });
      } else {
        $("#CON_ID").html("<option value=''>" + strSelecione + "</option>");
        $("#CON_ID").trigger("chosen:updated");
      }
    });
  }
}

function editar(rota, arrDados) {
  $(".btn-formulario").prop("disbled", true);

  $.ajax({
    url: $.trim(rota),
    dataType: "json",
    cache: false,
    type: "POST",
    data: arrDados,
  })
    .success(function (data) {
      $(".btn-filtro, .btn-formulario").prop("disbled", false);

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }
    })
    .fail(function (data) {
      $(".btn-filtro, .btn-formulario").prop("disbled", false);
      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function initEmpresasXEstruturas(filtro = true) {
  if (filtro) {
    $(".multiplos").multiselect(getOptions());

    $("#EMP_ID").on("change", function () {
      var arrSelecionadas = new Array();

      $("select[name='EMP_ID[]'] option:selected").each(function () {
        arrSelecionadas.push($(this).val());
      });

      if (arrSelecionadas.length > 0) {
        carregarEstruturasPorEmpresasMultiplos(arrSelecionadas);
      }
    });
  } else {
    $("#EMP_ID").change(function () {
      if ($.trim(this.value) != "") {
        $.ajax({
          url: $.trim($("#hddEmpresasDados").val()),
          dataType: "json",
          cache: false,
          type: "POST",
          data: {
            EMP_ID: $.trim(this.value),
          },
        })
          .success(function (data) {
            $(".btn-filtro, .btn-formulario").prop("disbled", false);

            if (data.error) {
              consultarPadraoExcessao();
              dialogAlert(strAtencao, data.error.msg, 6);
              return;
            }

            if (data.sucesso == "true") {
              var strHtml = "<option value=''>" + strSelecione + "</option>";

              for (var i = 0; i < data.arrContasBancarias.length; i++) {
                var strSelected = "";
                if (data.arrContasBancarias.length == 1) {
                  strSelected = "selected";
                }

                strHtml +=
                  "<option " +
                  strSelected +
                  " value='" +
                  data.arrContasBancarias[i].CON_ID +
                  "'>" +
                  data.arrContasBancarias[i].CON_Descricao +
                  "</option>";
              }

              $("#CON_ID").html(strHtml);
            } else {
              $("#CON_ID").html(
                "<option value=''>" + strSelecione + "</option>"
              );
              dialogAlert(strAtencao, data.mensagem, 6);
            }

            $("#CON_ID").trigger("chosen:updated");
          })
          .fail(function (data) {
            $(".btn-filtro, .btn-formulario").prop("disbled", false);
            dialogAlert(strAtencao, data.responseText, 6);
          });
      } else {
        $("#CON_ID").html("<option value=''>" + strSelecione + "</option>");
        $("#CON_ID").trigger("chosen:updated");
      }
    });
  }
}

function carregarEstruturasPorEmpresasMultiplos(arrEmpresas) {
  $("#EST_ID").multiselect("destroy");
  $("#EST_ID").html("");

  $.ajax({
    url: $.trim($("#empresas_x_estruturas").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      EMP_ID: arrEmpresas,
    },
  })
    .success(function (data) {
      $(".btn-filtro, .btn-formulario").prop("disbled", false);

      if (data.error) {
        $("#EST_ID").html("");
        $("#EST_ID").multiselect("refresh");

        consultarPadraoExcessao();
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      var strHtml = "";
      if (data.arrDados != undefined) {
        for (var i = 0; i < data.arrDados.length; i++) {
          var strSelected = "";
          if (data.arrDados.length == 1) {
            strSelected = "selected";
          }

          strHtml +=
            "<option " +
            strSelected +
            " value='" +
            data.arrDados[i].EST_ID +
            "'>" +
            data.arrDados[i].EST_Descricao +
            "</option>";
        }
      }

      $("#EST_ID").html(strHtml);
      $("#EST_ID").multiselect("refresh");
    })
    .fail(function (data) {
      $(".btn-filtro, .btn-formulario").prop("disbled", false);
      $("#EST_ID").html("");
      $("#EST_ID").multiselect("refresh");

      dialogAlert(strAtencao, data.responseText, 6);
    });
}

function startInterval(funcao) {
  return setInterval(funcao, 5000);
}

function stopInterval(interval) {
  clearInterval(interval);
  return false;
}

let cache = {};
async function getData(url) {
  let result = "";
  if (cache[url] !== undefined) return cache[url].value;

  await fetch(url)
    .then((response) => response.json())
    .then((json) => (cache[url] = { time: new Date(), value: json }));

  return cache[url].value;
}

function removerLinhaArquivoDocumentacaoClientes(r) {
  var row = $(r).closest("TR");
  document.getElementById("tblDocumentacaoClientes").deleteRow(row[0].rowIndex);

  $(".calcularPercentualComercial").trigger("blur");
}

function getModulosMultiSelects(
  consultar = true,
  carregarRotas = false,
  carregarAcoes = false,
  inputModulo = "MOD_ID",
  inputRota = "ROT_ID",
  inputAcao = "ACO_ID",
  mult_rot = false
) {

  $("#" + inputModulo).change(function () {
    if (carregarRotas) {
      if (consultar || mult_rot) {
        $("#" + inputRota).multiselect("destroy");
      }

      $("#" + inputRota).html("");
    }

    if (carregarAcoes) {
      if (consultar) {
        $("#" + inputAcao).multiselect("destroy");
      }

      $("#" + inputAcao).html("");
    }

    var arrModulos = new Array();

    if (consultar) {
      $("select[name='" + inputModulo + "[]'] option:selected").each(
        function () {

          arrModulos.push($(this).val());
        }
      );
    } else {
      if ($(this).val() != "") {

        arrModulos.push($("#" + inputModulo).val());
      }
    }

    if (arrModulos.length > 0) {
      $.ajax({
        url: $.trim($("#perfis_get_rotas_modulos").val()),
        dataType: "json",
        cache: false,
        data: {
          MOD_ID: arrModulos,
        },
        type: "POST",
      })
        .success(function (data) {
          if (data.error) {
            dialogAlert(strAtencao, data.error.msg, 6);
            return;
          }

          if (carregarRotas) {
            var strHtml = "";

            if (!$("#" + inputRota).prop("multiple")) {
              strHtml = "<option value=''>" + strSelecione + "</option>";
            }

            for (var i = 0; i < data.arrDados.length; i++) {
              strDescricao = data.arrDados[i].ROT_Nivel1;

              if ($.trim(data.arrDados[i].ROT_Nivel2) != "") {
                strDescricao += "/" + data.arrDados[i].ROT_Nivel2;
              }

              if ($.trim(data.arrDados[i].ROT_Nivel3) != "") {
                strDescricao += "/" + data.arrDados[i].ROT_Nivel3;
              }

              if ((arrModulos[0] == 9 && data.arrDados[i].ROT_ID == 91) ||
                (arrModulos[0] == 8 && data.arrDados[i].ROT_ID == 86) ||
                (arrModulos[0] == 6 && (data.arrDados[i].ROT_ID == 68 || data.arrDados[i].ROT_ID == 69 || data.arrDados[i].ROT_ID == 73)) ||
                (arrModulos[0] == 7 && (data.arrDados[i].ROT_ID == 77 || data.arrDados[i].ROT_ID == 173)) || mult_rot == false) {

                strHtml +=
                  "<option value='" +
                  data.arrDados[i].ROT_ID +
                  "'>" +
                  strDescricao +
                  " (" +
                  data.arrDados[i].MOD_Descricao +
                  ")</option>";

              }
            }

            $("#" + inputRota).append(strHtml);

            if (consultar || mult_rot) {
              $("#" + inputRota).multiselect("refresh");
            } else {
              $("#" + inputRota).selectpicker("refresh");
            }
          }

          if (carregarAcoes) {
            if (consultar) {
              $("#" + inputAcao).multiselect("refresh");
            } else {
              $("#" + inputAcao).selectpicker("refresh");
            }
          }
        })
        .fail(function (data) {
          if (carregarRotas) {
            if (consultar || mult_rot) {
              $("#" + inputRota).multiselect("refresh");
            } else {
              $("#" + inputRota).selectpicker("refresh");
            }
          }

          if (carregarAcoes) {
            if (consultar) {
              $("#" + inputAcao).multiselect("refresh");
            } else {
              $("#" + inputAcao).selectpicker("refresh");
            }
          }

          dialogAlert(strAtencao, data.responseText, 6);
        });
    } else {
      if (carregarRotas) {
        if (consultar || mult_rot) {
          $("#" + inputRota).multiselect("refresh");
        } else {
          $("#" + inputRota).selectpicker("refresh");
        }
      }

      if (carregarAcoes) {
        if (consultar) {
          $("#" + inputAcao).multiselect("refresh");
        } else {
          $("#" + inputAcao).selectpicker("refresh");
        }
      }
    }
  });

  if (carregarRotas) {
    $("#" + inputRota).change(function () {
      if (consultar) {
        $("#" + inputAcao).multiselect("destroy");
      } else {
        $("#" + inputAcao).selectpicker("destroy");
      }

      $("#" + inputAcao).html("");

      var arrRotas = new Array();
      var arrModulos = new Array();

      if (consultar || mult_rot) {
        if (!mult_rot) {

          $("select[name='" + inputModulo + "[]'] option:selected").each(
            function () {
              arrModulos.push($(this).val());
            }
          );
        }

        $("select[name='" + inputRota + "[]'] option:selected").each(
          function () {
            arrRotas.push($(this).val());
          }
        );
      } else {
        arrModulos.push($("#" + inputModulo).val());
        arrRotas.push($("#" + inputRota).val());
      }

      if (arrRotas.length > 0 && arrModulos.length > 0) {
        $.ajax({
          url: $.trim($("#perfis_get_acoes_rotas_modulos").val()),
          dataType: "json",
          cache: false,
          data: {
            MOD_ID: arrModulos,
            ROT_ID: arrRotas,
          },
          type: "POST",
        })
          .success(function (data) {
            if (data.error) {
              dialogAlert(strAtencao, data.error.msg, 6);
              return;
            }

            var strHtml = "";
            for (var i = 0; i < data.arrDados.length; i++) {
              strHtml +=
                "<option value='" +
                data.arrDados[i].ACO_ID +
                "'>" +
                data.arrDados[i].ACO_Descricao +
                "</option>";
            }

            $("#" + inputAcao).append(strHtml);

            if (consultar) {
              $("#" + inputAcao).multiselect("refresh");
            } else {
              $("#" + inputAcao).selectpicker("refresh");
            }
          })
          .fail(function (data) {
            if (consultar) {
              $("#" + inputAcao).multiselect("refresh");
            } else {
              $("#" + inputAcao).selectpicker("refresh");
            }

            dialogAlert(strAtencao, data.responseText, 6);
          });
      }
    });
  }
}

function salvarValorM2Selecionado() {
  if (
    $.trim($("#valorm2_selecionado").val()) == 0 ||
    $.trim($("#valorm2_selecionado").val()) == ""
  ) {
    $.notify("Valor m² precisa ser maior que zero.", "warn");
    return;
  } else {
    if ($('input[name="valorm2_selecteds[]"]:checked').length == 0) {
      $.notify("Nenhuma unidade selecionada.", "warn");
      return;
    } else {
      if (
        confirm(
          "Confirma a altereção dos valores para as unidades selecionadas?"
        )
      ) {
        $(".btn-formulario_selecionado").prop("disabled", true);
        var strLabel = $("#btnFluxo_selecionado").html();
        $("#btnFluxo_selecionado").html(strCarregando);
        preLoadingOpen();

        var ids_selecionados = new Array();

        $('input[name="valorm2_selecteds[]"]:checked').each(function () {
          ids_selecionados.push($(this).val());
        });

        ids_selecionados = ids_selecionados.join(", ");

        $.ajax({
          url: $.trim(
            $("#condicoes_tabelas_vendas_valorm2_unidades_selecionadas").val()
          ),
          dataType: "json",
          cache: false,
          type: "POST",
          data: {
            ids_selecionados: ids_selecionados,
            valorm2: $("#valorm2_selecionado").val(),
            tipoConta: $("#CON_Calculo").val(),
          },
        }).success(function (data) {
          $(".btn-formulario_selecionado").prop("disabled", false);
          $("#btnFluxo_selecionado").html(strLabel);
          $("#valorm2_selecionado").val("");
          $("#valorm2_selecionado").attr("disabled", "disabled");
          preLoadingClose();

          if (data.status == "success") {
            $.notify(data.message, "success");
            consultarTabelasVendasComercial();

            setTimeout(() => {
              var checkbox = $(".valorm2_selecteds");

              for (var i = 0; i < checkbox.length; i++) {
                checkbox[i].addEventListener("click", function () {
                  if (
                    $('input[name="valorm2_selecteds[]"]:checked').length > 0
                  ) {
                    $("#valorm2_selecionado").removeAttr(
                      "disabled",
                      "disabled"
                    );
                  } else {
                    $("#valorm2_selecionado").attr("disabled", "disabled");
                  }
                });
              }
            }, 2000);
          } else {
            dialogAlert(strAtencao, data.error, 6);
            return;
          }
        });
      }
    }
  }
}

function buscaReservas() {

  preLoadingOpen();

  $.ajax({
    url: $.trim($("#integracao_cv_busca_reservas").val()),
    dataType: "html",
    cache: false,
    type: "POST",
    data: {},
  }).success(function (data) {

    preLoadingClose();

    // if (data.status == "success") {

    $("#detalhes-reserva-corpo").html(data);
    $("#detalhes-reserva-titulo").html("Últimas reservas encontradas");

    $('#detalhes-reservas').modal('show');

    // dialogAlert(strInformacao, data.mensagem, 4);

    // } else {

    // dialogAlert(strAtencao, data.error, 6);
    // return;

    // }

  })
    .fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });;

}

function buscaReservaIndividual(id_reserva) {

  preLoadingOpen();

  $.ajax({
    url: $.trim($("#integracao_cv_busca_reserva_individual").val()),
    dataType: "html",
    cache: false,
    type: "POST",
    data: {
      id_reserva: id_reserva
    },
    beforeSend: function () {
      preLoadingOpen();
      $('#detalhes-reservas').modal('hide');
    },
  }).success(function (data) {

    preLoadingClose();

    $("#detalhes-reserva-corpo").html(data);
    $("#detalhes-reserva-titulo").html("Resumo e integração da reserva");

    $('#detalhes-reservas').modal('show');

  })
    .fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });

}

function gerarContratoIntegracao(id_reserva) {

  var selCorrecao = new Array();
  var selJuros = new Array();
  var error = 0;
  
  $("select[name='SEL_Correcao[]']").each(function() {
    if($(this).val() == ""){
      error++;
    } else {
      selCorrecao.push($(this).val());
    }    
  });

  $("select[name='SEL_Juros[]']").each(function() {
    if($(this).val() == ""){
      error++;
    } else {
      selJuros.push($(this).val());
    }
  });    

  if (
    $.trim($("#IND_ID").val()) == "" || $.trim($("#INDPOS_ID").val()) == "" ||
    $.trim($("#CTO_PeriodicidadeCorrecao").val()) == "" || $.trim($("#data_emissao").val()) == "" ||
    $.trim($("#data_base").val()) == "" || error > 0
  ) {
    $.notify("Todos os campos precisam ser preenchidos!", "warn");
    return;
  }

  var correcao_juros = new Array();
  correcao_juros.push({correcao: selCorrecao, juros: selJuros});  

  preLoadingOpen();

  $.ajax({
    url: $.trim($("#integracao_cv_gerar_contrato").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      id_reserva: id_reserva,
      indexador: $.trim($("#IND_ID").val()),
      indexador_pos: $.trim($("#INDPOS_ID").val()),
      periodicidade_correcao: $.trim($("#CTO_PeriodicidadeCorrecao").val()),
      data_emissao: $.trim($("#data_emissao").val()),
      data_base: $.trim($("#data_base").val()),
      correcao_juros: correcao_juros
    },
    beforeSend: function () {
      preLoadingOpen();
      $('#detalhes-reservas').modal('hide');
    },
  }).success(function (data) {
    preLoadingClose();

    if (data.status == "success") {
      $.notify(data.message, "success");      

      setTimeout(() => {
          location.replace(location.pathname);
      }, 50);
      
    } else {
      $("#detalhes-reserva-corpo").html("<p>" + data.message + "</p>");
      $("#detalhes-reserva-titulo").html("Atenção!");
      $('#detalhes-reservas').modal('show');
    }

  }).fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
  });

}

function isNumber(evt) {
  var theEvent = evt || window.event;
  var key = theEvent.keyCode || theEvent.which;            
  var keyCode = key;
  key = String.fromCharCode(key);          
  if (key.length == 0) return;
  var regex = /^[0-9.,\b]+$/;            
  if(keyCode == 188 || keyCode == 190){
     return;
  }else{
     if (!regex.test(key)) {
        theEvent.returnValue = false;                
        if (theEvent.preventDefault) theEvent.preventDefault();
     }
   }    
}

function abreModalOrdenarSecoesTerrenoVisualizar(){

  $.ajax({
    url: $.trim($("#terrenos_visualizar_ordenar_secoes").val()),
    dataType: "html",
    cache: false,
    type: "POST",
    data: {
    },
  }).success(function (data) {

    if (data) {
      
      $("#ordenar-secoes-corpo").html("<p>" + data + "</p>");
      $('#ordenar-secoes').modal('show');
    }

  }).fail(function (data) {
    preLoadingClose();
    dialogAlert(strAtencao, data.responseText, 6);
  });

}

function salvarOrdemSecoes(){

  $.ajax({
    url: $.trim($("#terrenos_visualizar_ordenar_secoes_salvar").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      ORD_Ordenacao: $.trim($("#ordem_final").val()),
    },
  }).success(function (data) {

    if (data.status == "success") {
      $.notify(data.message, "success");
      $('#ordenar-secoes').modal('hide');
      location.reload(true);
    }

  }).fail(function (data) {
    dialogAlert(strAtencao, data.responseText, 6);
  });

}

function alterarOrdenacaoOrdenador(...divIds) {
  const $container = $('.item-secao2');
  divIds.forEach(divId => {

    if(divId.includes('-')){
      
      divId = divId.split('-')[0]
      
      $('#'+divId+" .container-list")[0].lastElementChild.lastChild.className = 'fa fa-eye-slash';
    }

    $container.filter(`#${divId}`).detach().appendTo($container.parent());
  });
}

function alterarOrdenacao(...divIds) {
  const $container = $('.item-secao');
  divIds.forEach(divId => {

    if(divId.includes('-')){

      divId = divId.split('-')[0];
      $container.filter(`#${divId}`).detach();
    }else{

      $container.filter(`#${divId}`).detach().appendTo($container.parent());
    }
  });
}

function verificaExistenciaCorretor(modalTerreno = null){
  
  let COR_ID = $.trim($("#COR_ID").val()); 
  let COR_Email = $.trim($("#COR_Email").val()); 
  let GRC_Nome = $.trim($("#GRC_Nome").val());
  let GRC_Telefone = $.trim($("#GRC_Telefone").val());
  let GRC_Celular = $.trim($("#GRC_Celular").val());
  let GRC_Creci = $.trim($("#GRC_Creci").val());
  let SEL_SimNao = $.trim($("#SEL_SimNao").val());

  if(GRC_Telefone == "" || COR_Email == "" || GRC_Nome == "" || GRC_Celular == "" || GRC_Creci == ""){
    $.notify("Preencha todos os campos obrigatórios!", "warn");
    return;
  }

  $.ajax({
    url: $.trim($("#corretor_verifica_existencia").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      COR_ID: COR_ID,
      COR_Email: COR_Email,
      GRC_Nome: GRC_Nome,
      GRC_Celular: GRC_Celular     
    },   
  }).success(function (data) {
    if (data.status == "success") {
      if(modalTerreno == null){
        if(data.campo == "email"){
          $("#novo-corretor-corpo").html(data.message);
          $("#btn-cadastrar-corretor").attr("disabled", "disabled");
          $('#novo-corretor').modal('show');      
        }else{
          $("#novo-corretor-corpo").html(data.message);
          $("#btn-cadastrar-corretor").removeAttr("disabled", "disabled");
          $('#novo-corretor').modal('show');       
        }             
      }else{
        if(data.campo == "email"){
          $("#novo-corretor-corpo2").html(data.message);
          $("#btn-cadastrar-corretor").attr("disabled", "disabled");
          $('#novo-corretor2').modal('show');         
        } else {
          $("#novo-corretor-corpo2").html(data.message);
          $("#btnSalvar").removeAttr("disabled", "disabled");
          $('#novo-corretor2').modal('show');       
        }                        
      }
    }else{      
      if(modalTerreno == null){
        const form = document.getElementById('frmFormulario');
        form.submit();  
      } else {
          $.ajax({
            url: $.trim($("#corretores_salvar").val()),
            dataType: "json",
            cache: false,
            type: "POST",
            data: {
              COR_Email: COR_Email,
              GRC_Nome: GRC_Nome,
              GRC_Telefone: GRC_Telefone,
              GRC_Celular: GRC_Celular,
              GRC_Creci: GRC_Creci,
              SEL_SimNao: "N",
              modal: true
            },   
          }).success(function (data) {    

            if (data.status == "success") {        
              $('#close-novo-corretor').trigger('click');
              $.notify(data.message, "success"); 
              location.reload(true);     
            } 
        
          }).fail(function (data) {      
              dialogAlert(strAtencao, data.responseText, 6);
          });
      }
    }
  }).fail(function (data) {      
    dialogAlert(strAtencao, data.responseText, 6);
  });
}

function abreModalCadastroCorretor(){

  $.ajax({
    url: $.trim($("#corretores_novo").val()),
    dataType: "html",
    cache: false,
    type: "POST",
    data: {
      modal: "true"
    },
  }).success(function (data) {

    if (data) {
      
      $("#novo-corretor-corpo").html("<p>" + data + "</p>");
      $('#novo-corretor').modal('show');
    }

  }).fail(function (data) {
    preLoadingClose();
    dialogAlert(strAtencao, data.responseText, 6);
  })
}

// Checks whether an extension is included in the array
function isExtension(ext, extnArray) {
    var result = false;
    var i;
    if (ext) {
        ext = ext.toLowerCase();
        for (i = 0; i < extnArray.length; i++) {
            if (extnArray[i].toLowerCase() === ext) {
                result = true;
                break;
            }
        }
    }
    return result;
}

function salvarParametrosPlanejamento() {
  $(".btn-formulario").prop("disabled", true);
  var strLabel = $("#btnSalvarCompras").html();
  $("#btnSalvarCompras").html(strCarregando);
  preLoadingOpen();

  var strOrcamentoPorUsuario = strNao;
  

  if ($("#PAR_OrcamentoPorUsuario").is(":checked")) {
    strOrcamentoPorUsuario = strSim;
  }


  $.ajax({
    url: $.trim($("#grupos_empresas_parametros_planejamento").val()),
    dataType: "json",
    cache: false,
    data: {
      PAR_OrcamentoPorUsuario: $.trim(strOrcamentoPorUsuario),
    },
    type: "POST",
  })
    .success(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvarCompras").html(strLabel);
      preLoadingClose();

      if (data.error) {
        dialogAlert(strAtencao, data.error.msg, 6);
        return;
      }

      $.notify(data.mensagem, "success");
    })
    .fail(function (data) {
      $(".btn-formulario").prop("disabled", false);
      $("#btnSalvarCompras").html(strLabel);
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });
}
function formatarMoeda(valor, digitos = 2){
  return valor.toLocaleString('pt-br', {minimumFractionDigits: digitos});
}


function HelperCampos(strRota){
	$.ajax({  
		url:$.trim(strRota),
		 method:"POST", 
		 dataType:"json",
		 data:{},  
		 success:function(data){ 
      sizes = BootstrapDialog.SIZE_WIDE;

      if (data.strTamanho == 'SIZE_NORMAL') {
        sizes = BootstrapDialog.SIZE_NORMAL;
      } else if (data.strTamanho == 'SIZE_SMALL') {
        sizes = BootstrapDialog.SIZE_SMALL;
      } else if (data.strTamanho == 'SIZE_LARGE') {
        sizes = BootstrapDialog.SIZE_LARGE;
      } 

      BootstrapDialog.show({
        title: data.strTitulo,
        message: data.strHtml,
        size: sizes,
        type: BootstrapDialog.TYPE_PRIMARY,
        buttons: [
          {
            label: "Fechar",
            id: "btn-confirmar-nao",
            action: function (dialogItself) {
              dialogItself.close();
            },
          }
        ]

      });

			
		 },
		error:function(data){
      BootstrapDialog.show({
        title: 'Atenção',
        message: data.responseText,
        size: BootstrapDialog.SIZE_WIDE,
        type: BootstrapDialog.TYPE_DANGER

      });
		},
		 cache: false
	});
}

function EditarHelperCampos(strRota){
	
  $.ajax({  
		url:$.trim(strRota),
		 method:"POST", 
		 dataType:"json",
		 data:{},  
		 success:function(data){ 
      sizes = BootstrapDialog.SIZE_WIDE;

      if (data.strTamanho == 'SIZE_NORMAL') {
        sizes = BootstrapDialog.SIZE_NORMAL;
      } else if (data.strTamanho == 'SIZE_SMALL') {
        sizes = BootstrapDialog.SIZE_SMALL;
      } else if (data.strTamanho == 'SIZE_LARGE') {
        sizes = BootstrapDialog.SIZE_LARGE;
      }       
      BootstrapDialog.show({
        title: data.strTitulo,
        message: data.strHtml,
        size: sizes,
        type: BootstrapDialog.TYPE_PRIMARY,
        buttons: [
          {
            label: "Fechar",
            id: "btn-confirmar-nao",
            action: function (dialogItself) {
              dialogItself.close();
            },
          },
          {
            label: "Salvar",
            cssClass: "btn-primary",
            id: "btn-confirmar-sim",
            data: {
              js: "btn-confirm",
              "user-id": "3",
            },
            action: function (dialogItself) {
              
               
              if ($.trim($('#HEL_Modulo').val()) == ''){
                $.notify("Campo Módulo é obrigatório","error");
                document.getElementById("HEL_Campo").focus();
                return;
              }else if ($.trim($('#HEL_Campo').val()) == ''){ 
                $.notify("Nome do Campo é obrigatório","error");
                document.getElementById("HEL_Campo").focus();
                return;
              }else if ($.trim($('#HEL_Titulo').val()) == ''){
                $.notify("Título do Helper é obrigatório","error");
                document.getElementById("HEL_Titulo").focus();
                return;
              } 
              $.ajax({  	
		
                url:data.rotasalvar,
                method:"POST", 
                dataType:"json",
                data:{
                  HEL_ID: $('#HEL_ID').val(),
                  HEL_Modulo: $('#HEL_Modulo').val(), 
                  HEL_Campo: $('#HEL_Campo').val(),
                  HEL_Titulo: $('#HEL_Titulo').val(),
                  HEL_FlagTamanho: $('#HEL_FlagTamanho').val(),
                  HEL_LinkVideo: $('#HEL_LinkVideo').val(),
                  HEL_Descricao: $("iframe").contents().find(".wysihtml5-editor").html()
                },  
                success:function(datadois){  
                  if(datadois.error){
                    BootstrapDialog.show({
                      title: 'Atenção',
                      message: datadois.error.msg,
                      size: BootstrapDialog.SIZE_WIDE,
                      type: BootstrapDialog.TYPE_DANGER
              
                    });
                    return;
                  }

                  if(datadois.status=='success'){
                    $.notify(datadois.mensagem,"success");
                    dialogItself.close();
                  }

                },error:function(datadois){
                  BootstrapDialog.show({
                    title: 'Atenção',
                    message: datadois.responseText,
                    size: BootstrapDialog.SIZE_WIDE,
                    type: BootstrapDialog.TYPE_DANGER
            
                  });
                },
                 cache: false
              });       
            },
          },
        ]

      });

      setTimeout(function () {
				$('.textarea').wysihtml5();
				
			}, 500);
		 },
		error:function(data){
      BootstrapDialog.show({
        title: 'Atenção',
        message: data.responseText,
        size: BootstrapDialog.SIZE_WIDE,
        type: BootstrapDialog.TYPE_DANGER

      });
		},
		 cache: false
	});
}

function gerenciarCredenciaisD4S() {  

  $.ajax({
    url: $.trim($("#integracao_d4s_gerenciar_credenciais").val()),
    dataType: "html",
    cache: false,
    type: "POST",
    data: {},
  }).success(function (data) {

    preLoadingClose();

    // if (data.status == "success") {

    $("#detalhes-d4s-corpo").html(data);
    $("#detalhes-d4s-titulo").html("Gerenciar credenciais D4Sign");

    $('#gerenciar-d4s').modal('show');

    // dialogAlert(strInformacao, data.mensagem, 4);

    // } else {

    // dialogAlert(strAtencao, data.error, 6);
    // return;

    // }

  })
    .fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });;

}

function cadastrarCredenciaisD4S() {

  preLoadingOpen();

  $.ajax({
    url: $.trim($("#integracao_d4s_cadastrar_credenciais").val()),
    dataType: "html",
    cache: false,
    type: "POST",
    data: {},
  }).success(function (data) {

    preLoadingClose();

    // if (data.status == "success") {

    $("#detalhes-d4s-corpo").html(data);
    $("#detalhes-d4s-titulo").html("Cadastrar credencial D4Sign");

    $('#gerenciar-d4s').modal('show');    

  })
    .fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });;

}

function salvarCredenciaisD4S() {
  
  var erros = 0;
  
  $(".required").each(function () {
    if ($(this).val() == "") {      
      erros++;
    }
  });

  if (erros > 0) {    
    $.notify("O preenchimento de todos os campos é obrigatório!", "warn");
    return;    
  }    

  preLoadingOpen();

  let tokenAPI = $.trim($("#tokenAPI").val());  
  let cryptKey = $.trim($("#cryptKey").val());
  let EMP_ID = $.trim($("#EMP_ID").val());

  $.ajax({
    url: $.trim($("#integracao_d4s_salvar_credenciais").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      tokenAPI: tokenAPI,
      cryptKey: cryptKey,
      EMP_ID: EMP_ID
    },
    beforeSend: function () {
      preLoadingOpen();
      $("#btn-check-credentials").attr("disabled", "disabled");
    },
  }).success(function (data) {    

    preLoadingClose();
    
    $("#btn-check-credentials").removeAttr("disabled");
        
    if (data.status == "error") {
      dialogAlert(strAtencao, data.message, 6);
      return;
    }

    $.notify(data.message, "success");
    $('#gerenciar-d4s').modal('hide');

    setTimeout(() => {      
      $('#btn-gerenciar-credenciais').trigger('click');      
    }, 1000);    
    
  })
    .fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });

}

function adicionarSignatario() {

  if($.trim($("#SIG_Email").val()) == ""){
    $.notify("O e-mail do signatário precisa ser preenchido!", "warn");
    return;
  }

  preLoadingOpen();

  $.ajax({
    url: $.trim($("#integracao_d4s_adicionar_signatario").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      PRO_ID: $.trim($("#PRO_ID").val()),
      MIN_ID: $.trim($("#MIN_ID").val()),
      SIG_Email: $.trim($("#SIG_Email").val())
    },
  }).success(function (data) {
    preLoadingClose();    
    $.notify(data.message, data.status);    

    if(data.status == "success"){
      $("#SIG_Email").val("");

      // Crie o bloco de HTML complexo
      var htmlBlock = '<li class="list-group-item cursor-move" data-id="'+ btoa(data.sig_id) +'">' +
        '<span class="badge alert-danger" style="cursor:pointer;" ' +
        'onclick="removerSignatario(\'' + btoa(data.sig_id) + '\')"><i class="fa fa-trash"></i></span>' +
        data.sig_email +
        '<div class="input-group input-group-sm" style="margin-top:7px;">' +
        '<span class="input-group-addon" id="sizing-addon3">Este signatário irá:</span>' +
        '<select class="form-control" onchange="atualizaActSignatario(\'' + btoa(data.sig_id) + '\', this.value)">' +
          '<option value="1">Assinar</option>' +
          '<option value="2">Aprovar</option>' +
          '<option value="3">Reconhecer</option>' +
          '<option value="4">Assinar como parte</option>' +
          '<option value="5">Assinar como testemunha</option>' +
          '<option value="6">Assinar como interveniente</option>' +
          '<option value="7">Acusar recebimento</option>' +
          '<option value="8">Assinar como Emissor, Endossante e Avalista</option>' +
          '<option value="9">Assinar como Emissor, Endossante, Avalista, Fiador</option>' +
          '<option value="10">Assinar como fiador</option>' +
          '<option value="12">Assinar como responsável solidário</option>' +
          '<option value="13">Assinar como parte e responsável solidário</option>'            
      htmlBlock += '</select></div></li>';
        
        $("#listaSignatarios").append(htmlBlock);
    }

  })
    .fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });

}

function atualizaActSignatario(idSig = null, act = null, ordem = null){  

  $.ajax({
    url: $.trim($("#integracao_d4s_atualiza_act_signatario").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      SIG_ID: idSig,
      SIG_Act: act, 
      ordenacao: ordem
    },
  }).success(function (data) {

    $.notify(data.message, data.status);    

  })
    .fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });;
}

function removerSignatario(idSig){  

  $.ajax({
    url: $.trim($("#integracao_d4s_remover_signatario").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      SIG_ID: idSig      
    },
  }).success(function (data) {

    $.notify(data.message, data.status);
    $("#listaSignatarios").find("li[data-id='" + idSig + "']").remove();    

  })
    .fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });;
}

function enviarParaAssinatura(idSig = null){  

  const corpoDocumento = {};
  $('input, select').each(function() {
      var name = $(this).attr('name');
      var value = $(this).val();
      corpoDocumento[name] = value;
  });

  $.ajax({
    url: $.trim($("#integracao_d4s_enviar_para_assinatura").val()),
    dataType: "json",
    cache: false,
    type: "POST",
    data: {
      corpoDocumento
    },
    beforeSend: function () { 
      preLoadingOpen();
      $("#sendToSignature").attr("disabled", "disabled");
    },
  }).success(function (data) {

    $.notify(data.message, data.status);
    $("#sendToSignature").removeAttr("disabled");
    preLoadingClose();
    $('#modal-enviado-para-assinatura').modal('show');

  })
    .fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });;
}

  function AtualizarEntregaPedido(PDI_ID, PDI_Entregue){
    $.ajax({
      url: $.trim($('#itens_pedidos_atualizar_entrega').val()),
      dataType: 'json',
      cache: false,
      data: {
        PDI_ID: PDI_ID,
        PDI_Entregue: PDI_Entregue,
      },
      type: 'POST',
    }).success(function(data){

    if(data.status == 'success'){

      $.notify(data.message, data.status);
      consultarItensPedidos($.trim($('#PED_ID').val()));
    }else{

        dialogAlert(data.status, data.message, 6);
      }
    });
  }

  function validarDadosCadastraisCorretor(value, tipo){
    $.ajax({
      url: $.trim($('#comercial_corretores_validar_dados').val()),
      dataType: 'json',
      cache: false,
      data: {
        value: value,
        tipo: tipo,
        SGP_TipoPessoa: $('#SGP_TipoPessoa').val(),
        COR_ID: $('#COR_ID').val(),
      },
      type: 'POST',
    }).success(function(data){
      
      if(data.status == 'success'){

        $(data.tipo).val('true');

        if(tipo == 'cpfcnpj'){

          validarCPFCNPJ(value);

            setTimeout(() => {
              if(resultValidaCPFCNPJ == false){
                $(data.tipo).val('false');
              }
            }, 200);

          }

      }else{

        $(data.tipo).val('false');
        $.notify(data.message, data.status);
      }
    });
  }

function consultarMapaEmpreendimento() {
  preLoadingOpen();
  $("#consultar_mapa_empreendimento").html(strCarregando);

  $.ajax({
    url: $.trim($("#estruturas_mapa_empreendimento").val()),
    data: {
      EST_ID: $.trim($("#EST_ID").val())
    },
    dataType: 'html',
    method: 'POST',
    success: function (data) {
      
      $("#consultar_mapa_empreendimento").html(data);

      preLoadingClose();
    },
  });
}

function telaCheia(){
  $('.modal-dialog').addClass('modal-full-screen');
}

function reprovarPedidoDireto(PED_ID) {
  $.post(
    $.trim($("#hddPedidosReprovar").val()) +
    "/" +
    PED_ID,
    function (data) {
      
      if (data.sucesso == "true") {

        if ($.trim($("#PED_ID").val()) != "") {
          setTimeout(function () {
            redir("", "parent");
          }, 2000);
        } 
      } else {
        $.notify(data.mensagem, "error");
      }
    },
    "json"
  );
}

function recarregarTotais(linha = '', spnTotal = '', totalRegistros = '', consultar = ''){
  if ($.trim(spnTotal) != "" && $.trim(totalRegistros) != ''){
    $('#'+spnTotal).html(totalRegistros);
  }

  if ($.trim(linha) != ''){
    $('#'+linha).remove();
  }

  if ($.trim(consultar) != ''){
    eval(consultar);
  }
  
  $("input[type=checkbox][id='chkTodos']").prop('checked', false);
}

function opemImportacaoFatorCalculo() {  
    $('#modal-importar-fator-falculo').modal('show');
}

function importarFatorCalculo() {
  
    var formData = new FormData(document.getElementById("formdeimportacao"));
    console.log("🚀 ~ importarFatorCalculo ~ formData:", formData)

    $.ajax({
      url: $.trim($("#fator_calculo_terreno_cidades_importar").val()),
      type: "POST",
      dataType: "json",
      data: formData,
      beforeSend: function () {
        preLoadingOpen();
      },
      success: function (data) {
        preLoadingClose();
        $("#modal-importar-fator-falculo").modal("toggle");
        if (data.error) {
          dialogAlert(strAtencao, data.error.msg, 6);
        } else {
          //alert(data)
          // dialogAlert(strAtencao, data.success.msg, 6);
          //$('#modalBootstrapDialogDetail').modal('toggle');
          $.notify(data.success.msg, "success");
          $("#btnAtualizar").trigger("click");
        }
      },
      cache: false,
      contentType: false,
      processData: false,
      xhr: function () {
        // Custom XMLHttpRequest
        var myXhr = $.ajaxSettings.xhr();
        if (myXhr.upload) {
          // Avalia se tem suporte a propriedade upload
          myXhr.upload.addEventListener(
            "progress",
            function () {
              /* faz alguma coisa durante o progresso do upload */
              //$('#modalBootstrapDialogDetail').modal('toggle');
              //preLoadingOpen();
            },
            false
          );
        }
        return myXhr;
      },
    })
    .fail(function (data) {
      preLoadingClose();
      dialogAlert(strAtencao, data.responseText, 6);
    });;;
}
  
// Essa função alterna o icone e adiciona o '-' nos boxes que forem ser ocultos
function AlterarVisibilidade(e){

  var order = [];

  var strOrdem = $("#ordem_final").val();
  var arrayOrdem = strOrdem.split(',').map(item => item.trim());

  if(e.lastChild.className == 'fa fa-eye'){

    e.lastChild.className = 'fa fa-eye-slash';

    arrayOrdem.forEach( id => {
      
      var idNovo = id

      if(idNovo == e.offsetParent.id){
        
        idNovo = id + "-"
      }

      order.push(idNovo);
        
    });

  }else{
    
    e.lastChild.className = 'fa fa-eye';

    arrayOrdem.forEach(id => {

      var idNovo = id;

      if(idNovo == e.offsetParent.id+"-"){
        
        idNovo = e.offsetParent.id;
      }

      order.push(idNovo);
        
    });
  }

  var ordem_final = order.map(item => `${item}`).join(', ');
  
  $("#ordem_final").val(ordem_final);

}
function ProcessarBaixaAssociativo(strRota){
  $("#btnSalvar").prop("disabled", false);
  $("#btnSalvar").html("Processando Baixas, Aguarde...");

  $.ajax({  
		url:$.trim(strRota),
		 method:"POST", 
		 dataType:"json",
		 data:{},  
    success:function(data){ 
      if(data.error){
        dialogAlert('Atenção', data.error.msg, 6);
      }

      if(data.status=='success'){
        $.notify("Informações atualizadas com sucesso", "success");
        $(".modal").modal("hide");
        consultarContratosCarteirasAssociativoParcelas();
        
      }
      
    },
    error:function(data){
      dialogAlert('Atenção', data.responseText, 6);
		},
		 cache: false
	});
}

function detalhesPorFormulario(
  strRota,
  strTitulo,
  strTipo,
  arrCampos,
  frmFormulario = 'frmFormulario'
) {
  var types = BootstrapDialog.TYPE_DEFAULT;
  if (strTipo == 2) {
    types = BootstrapDialog.TYPE_INFO;
  } else if (strTipo == 3) {
    types = BootstrapDialog.TYPE_PRIMARY;
  } else if (strTipo == 4) {
    types = BootstrapDialog.TYPE_SUCCESS;
  } else if (strTipo == 5) {
    types = BootstrapDialog.TYPE_WARNING;
  } else if (strTipo == 6) {
    types = BootstrapDialog.TYPE_DANGER;
  }

  var data = new FormData($('#'+frmFormulario)[0]);
  console.log(data);


  $.ajax({
    url: strRota,
    dataType: 'json',
    cache: false,
    enctype: 'multipart/form-data',
    data: data,
    type: 'POST',
    processData: false,
    contentType: false,
  }).success(function(data){
    $("#btnConfirmPadraoYes").prop("disabled", false);
    $("#btnConfirmPadraoYes").html(strLabelSim);

    if (data.error) {
      dialogAlert(strAtencao, data.error.msg, 6);
      return;
    }

    if (data.strTitulo != undefined) {
      if ($.trim(data.strTitulo) != "") {
        strTitulo = data.strTitulo;
      }
    }

    BootstrapDialog.show({
      id: "modalBootstrapDialogDetail",
      size: BootstrapDialog.SIZE_WIDE,
      type: types,
      title: $.trim(strTitulo),
      message: data.strHtml,
    });

    setTimeout(function () {
      setInitFunctions();
    }, 500);

  }).fail(function(data){
    $("#btnConfirmPadraoYes").prop("disabled", false);
    $("#btnConfirmPadraoYes").html(strLabelSim);
    $("#dialogConfirmBootstrap").modal("hide");
    preLoadingClose();

    dialogAlert(strAtencao, data.responseText, 6);
  });	
}
function marcarCancelarBoletos(chkTodosID, cssID) {
  if ($("#" + chkTodosID).is(":checked")) {
    $("." + cssID).each(function () {
      this.checked = true;
    });
    $("#btnCarteirasBoletosCancelar").show();
  } else {
    $("." + cssID).each(function () {
      this.checked = false;
    });

    $("#btnCarteirasBoletosCancelar").hide();
  }
}
function checarBtnBoletosCancelar() {
  $("#btnCarteirasBoletosCancelar").hide();
  $(".chkCarteiraBoletos").each(function () {
    if (this.checked) {
      $("#btnCarteirasBoletosCancelar").show();
      return;
    }
  });
}