// Starter JavaScript for disabling form submissions if there are invalid fields
(function () {
  'use strict'

  // Fetch all the forms we want to apply custom Bootstrap validation styles to
  var forms = document.querySelectorAll('.needs-validation')

  // Loop over them and prevent submission
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        } else {
          //console.log(form.msgid.value);
          //manually validation
          if (form.msgcontent.value.length === 0) {
            $("textarea#msgcontent").removeClass('valid');
            $("textarea#msgcontent").addClass('is-invalid');
            $(".invalid-feedback").html("Vui long nhap noi dung tin");
            event.preventDefault()
            event.stopPropagation()
          } else {

            var data = JSON.parse(localStorage.getItem("msgplays"));

            if (form.msgid.value === 'new') {
              //new
              var len = data.data.length;
              if (len > 0) len = Number(data.data[data.data.length - 1][0]);
              len++;
              //
              //console.log(len);
              //nhan dang khach
              var cusc = 'chua nhan';
              var idex = form.msgcontent.value.indexOf(".");
              if (idex == -1) idex = form.msgcontent.value.indexOf(" ");
              if (idex > -1) cusc = form.msgcontent.value.substring(0,idex);
              data.data.push([len.toString(), cusc,
              form.msgcontent.value, 'new', '']);
              //localStorage.setItem("msgplays",JSON.stringify(data));
            } else {
              var index = data.data.findIndex(item => item[0] === form.msgid.value);
              //console.log(msg);
              data.data[index][2] = form.msgcontent.value;
              //nhan dang khach
              var cusc = data.data[index][1];
              var idex = form.msgcontent.value.indexOf(".");
              if (idex == -1) idex = form.msgcontent.value.indexOf(" ");
              if (idex > -1) cusc = form.msgcontent.value.substring(0,idex);
              data.data[index][1] = cusc;
              //
              if (data.data[index][3] === 'error')
              data.data[index][3] = 'edit';
              //localStorage.setItem("msgplays",JSON.stringify(data));
            }
            localStorage.setItem("msgplays", JSON.stringify(data));
            //
            //alert(form.msgcontent.value.length);
            form.classList.add('was-validated')
            //
          }

        }
        
        //form.classList.add('was-validated')
      }, false)
    })
})()