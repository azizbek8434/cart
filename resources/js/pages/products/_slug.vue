<template>
  <div class="card">
    <div class="card-body">
      <div class="row">
        <div class="col-md-4 col-sm-12">
          <img src="/images/1.jpg" class="card-img-top" alt="product name" />
        </div>
        <div class="col">
          <div class="card-detail">
            <div class="card-title" v-if="product.name">
              <h3>{{ product.name }}</h3>
            </div>
            <div class="card-price" v-if="product.price">
              <b>Price:</b>
              {{ product.price }}
            </div>
            <div class="card-text" v-if="product.description">
              <p>{{ product.description}}.</p>
            </div>
            <span class="badge badge-warning" v-if="!product.in_stock">Out of stock</span>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer" v-show="product.variations">
      <form @submit.prevent="add">
        <ProductVariation
          v-for="(variations, type) in product.variations"
          :type="type"
          :key="type"
          :variations="variations"
          v-model="form.variation"
        />
        <div class="input-group mt-5" v-if="form.variation">
          <select class="custom-select" v-model="form.quantity">
            <option :value="x" v-for="x in parseInt(form.variation.stock_count)" :key="x">{{ x }}</option>
          </select>
          <div class="input-group-append">
            <button class="btn btn-outline-primary" type="submit">Add to cart</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>
<script>
import { mapActions } from "vuex";
import ProductVariation from "../../components/products/ProductVariation";
export default {
  data() {
    return {
      product: [],
      form: {
        variation: "",
        quantity: 1
      }
    };
  },
  watch: {
    "form.variation"() {
      this.form.quantity = 1;
    }
  },
  components: {
    ProductVariation
  },
  methods: {
    ...mapActions({
      store: "storeCart"
    }),
    add() {
      this.store([
        {
          id: this.form.variation.id,
          quantity: this.form.quantity
        }
      ]);

      this.form = {
        variation: "",
        quantity: 1
      };
    }
  },
  mounted() {
    let uri = `/api/products/${this.$route.params.slug}`;
    axios.get(uri).then(response => {
      this.product = response.data.data;
    });
  }
};
</script>
<style scoped>
.card {
  padding: 20px;
}
.card-detail {
  padding: 10px;
}
.card-title {
  padding-bottom: 10px;
  border-bottom: 1px solid gainsboro;
}
</style>

