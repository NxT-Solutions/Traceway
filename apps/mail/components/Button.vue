<script setup>
defineProps({
  href: { type: String, default: '%%URL%%' },
})
</script>

<template>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 8px 0 24px;">
  <tr>
    <td class="tw-button" bgcolor="#c6df4a" style="border-radius: 10px; background-color: #c6df4a; mso-padding-alt: 13px 22px;">
      <a class="tw-button-link" :href="href" style="display: inline-block; padding: 13px 22px; border-radius: 10px; font-family: 'Avenir Next', 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 16px; line-height: 20px; font-weight: 600; color: #12202a; text-decoration: none; mso-line-height-rule: exactly;"><slot /></a>
    </td>
  </tr>
</table>
</template>
