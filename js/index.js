// Funções para controle do tamanho da fonte
function increaseFont() {
  const currentSize = parseInt(window.getComputedStyle(document.body).fontSize);
  document.body.style.fontSize = currentSize + 1 + "px";
}

function decreaseFont() {
  const currentSize = parseInt(window.getComputedStyle(document.body).fontSize);
  // Impede que a fonte fique muito pequena
  if (currentSize > 10) {
    document.body.style.fontSize = currentSize - 1 + "px";
  }
}

// Máscara para CPF/CNPJ (padrão de mercado — formatação posicional)
function mascara(input) {
  let v = input.value.replace(/\D/g, "").substring(0, 14);

  if (v.length <= 11) {
    // CPF: 000.000.000-00
    if (v.length > 9)      v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{1,2})$/, "$1.$2.$3-$4");
    else if (v.length > 6) v = v.replace(/^(\d{3})(\d{3})(\d{0,3})/, "$1.$2.$3");
    else if (v.length > 3) v = v.replace(/^(\d{3})(\d{0,3})/, "$1.$2");
  } else {
    // CNPJ: 00.000.000/0000-00
    if (v.length > 12)     v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})$/, "$1.$2.$3/$4-$5");
    else if (v.length > 8) v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{0,4})/, "$1.$2.$3/$4");
    else if (v.length > 5) v = v.replace(/^(\d{2})(\d{3})(\d{0,3})/, "$1.$2.$3");
    else if (v.length > 2) v = v.replace(/^(\d{2})(\d{0,3})/, "$1.$2");
  }

  input.value = v;
}

// Máscara para telefone
function handlePhone(event) {
  let value = event.target.value.replace(/\D/g, "");

  if (value.length <= 10) {
    // Formato: (00) 0000-0000
    value = value.replace(/(\d{2})(\d)/, "($1) $2");
    value = value.replace(/(\d{4})(\d)/, "$1-$2");
  } else {
    // Formato: (00) 00000-0000
    value = value.replace(/(\d{2})(\d)/, "($1) $2");
    value = value.replace(/(\d{5})(\d)/, "$1-$2");
  }

  event.target.value = value;
}
