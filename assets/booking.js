jQuery(function($){
  const form=$('.web-booking');if(!form.length)return;
  const start=$('#web_start'),hours=$('#web_hours'),date=$('#web_date'),status=$('#web-availability');
  const close=Number(form.attr('data-close')),product=Number(form.attr('data-product'));
  let slots=[];let request=null;
  function update(){
    const selected=start.val();
    start.find('option').each(function(){this.disabled=slots.length>0&&!slots.some(s=>s.time===this.value&&s.available);});
    if(start.find('option:selected').prop('disabled')){const first=start.find('option:not(:disabled)').first();if(first.length)start.val(first.val());}
    const h=Number((start.val()||'00:00').split(':')[0]);
    hours.find('option').each(function(){
      const n=Number(this.value);let ok=h+n<=close;
      if(slots.length){for(let i=0;i<n;i++){const t=String(h+i).padStart(2,'0')+':00';if(!slots.some(s=>s.time===t&&s.available))ok=false;}}
      this.disabled=!ok;
    });
    if(hours.find('option:selected').prop('disabled'))hours.val(hours.find('option:not(:disabled)').first().val()||'');
    const valid=!!start.val()&&!!hours.val()&&(!slots.length||slots.some(s=>s.time===start.val()&&s.available));
    form.closest('form.cart').find('button.single_add_to_cart_button').prop('disabled',!valid);
    if(slots.length){const free=slots.filter(s=>s.available).length;status.text(free+' of '+slots.length+' hourly slots available. Choose a starting time and consecutive duration.');}
  }
  function load(){
    slots=[];status.text('Checking availability…');
    if(request)request.abort();
    request=$.getJSON(webBooking.ajax,{action:'web_slots',nonce:webBooking.nonce,product_id:product,date:date.val()}).done(function(response){
      if(response.success){slots=response.data.slots;update();}
      else{status.text('Availability could not be checked. Please try again.');form.closest('form.cart').find('button.single_add_to_cart_button').prop('disabled',true);}
    }).fail(function(_,state){if(state!=='abort'){status.text('Availability could not be checked. Please try again.');form.closest('form.cart').find('button.single_add_to_cart_button').prop('disabled',true);}});
  }
  date.on('change',load);start.on('change',update);hours.on('change',update);load();
});
