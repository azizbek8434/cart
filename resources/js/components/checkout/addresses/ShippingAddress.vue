<template>
  <article class="pl-4">
    <h3 class="text-muted pt-2 mb-3">Billing address</h3>
    <template v-if="selecting">
      <ShippingAddressSelector
        :addresses="addresses"
        :selectedAddress="selectedAddress"
        @click="addressSelected"
      />
    </template>
    <template v-else-if="creating">
      <ShippingAddressCreator @cancel="creating = false" @store="created" />
    </template>
    <template v-else>
      <template v-if="selectedAddress">
        <p>
          <b>Name:</b>
          {{ selectedAddress.name }}
          <br />
          <b>Address:</b>
          {{ selectedAddress.address_1 }}
          <br />
          <b>City:</b>
          {{ selectedAddress.city }}
          <br />
          <b>Postalcode:</b>
          {{ selectedAddress.postal_code }}
          <br />
          Country: {{ selectedAddress.country.name }}
        </p>
        <br />
      </template>
      <template v-else>
        <p>No address</p>
        <br />
      </template>
      <div class="field">
        <p>
          <button
            type="button"
            @click.prevent="selecting = true"
            class="btn btn-primary btn-sm"
          >Change shipping address</button>
          <button
            type="button"
            @click.prevent="creating = true"
            class="btn btn-primary btn-sm"
          >Add an address</button>
        </p>
      </div>
    </template>
  </article>
</template>
<script>
import ShippingAddressSelector from "../addresses/ShippingAddressSelector";
import ShippingAddressCreator from "../addresses/ShippingAddressCreator";
export default {
  props: {
    addresses: {
      required: true,
      type: Array
    }
  },
  data() {
    return {
      selecting: false,
      creating: false,
      selectedAddress: null
    };
  },
  components: {
    ShippingAddressSelector,
    ShippingAddressCreator
  },
  watch: {
    defaultAddress(v) {
      if (v) {
        this.switchAddress(v);
      }
    },
    selectedAddress(address) {
      this.$emit("input", address.id);
    }
  },
  computed: {
    defaultAddress() {
      return this.addresses.find(a => a.default === 1);
    }
  },
  methods: {
    addressSelected(address) {
      this.switchAddress(address);
      this.selecting = false;
    },
    switchAddress(address) {
      this.selectedAddress = address;
    },
    created(address) {
      this.selectedAddress = address;
      this.creating = false;
      this.switchAddress(address);
    }
  }
};
</script>
<style scoped>
article {
  border-left: 1px solid gray;
}
.field {
  display: block;
}
</style>
