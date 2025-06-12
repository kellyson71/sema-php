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

// Máscara para CPF/CNPJ
function mascara(input) {
  let value = input.value.replace(/\D/g, "");

  if (value.length <= 11) {
    // Formato CPF: 000.000.000-00
    value = value.replace(/(\d{3})(\d)/, "$1.$2");
    value = value.replace(/(\d{3})(\d)/, "$1.$2");
    value = value.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
  } else {
    // Formato CNPJ: 00.000.000/0000-00
    value = value.replace(/^(\d{2})(\d)/, "$1.$2");
    value = value.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
    value = value.replace(/\.(\d{3})(\d)/, ".$1/$2");
    value = value.replace(/(\d{4})(\d)/, "$1-$2");
  }

  input.value = value;
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
